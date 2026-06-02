<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use App\Models\PedidoWebItem;
use App\Models\Comercio;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class PedidoWebController extends Controller
{
    public function store(Request $request)
    {
        // 1. VALIDACIÓN RIGUROSA (Evita errores de Base de Datos como el de Postgres)
        $request->validate([
            'comercio_id' => 'required|exists:comercios,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'items' => 'required|array|min:1',
            'tipo_entrega' => 'required|in:local,delivery',
            'telefono_contacto' => 'required_if:tipo_entrega,delivery|nullable|string|min:6',
            'metodo_pago' => 'required|in:efectivo,transferencia,mercadopago',
            'direccion_entrega' => 'required_if:tipo_entrega,delivery|nullable|string',
            'total_productos' => 'required|numeric',
            'total_final' => 'required|numeric',
        ]);

        $user = $request->user();

        // Resolvemos el nombre del cliente según el guard autenticado
        $nombreCliente = 'Cliente Invitado';
        if ($user) {
            $nombreCliente = $user->name ?? ($user->nombre . ' ' . $user->apellido);
        } elseif ($request->cliente_nombre) {
            $nombreCliente = $request->cliente_nombre;
        }

        // 2. VALIDACIÓN DE STOCK (Evita compras con stock insuficiente o manipulación del frontend)
        $sucursalId = $request->sucursal_id;
        foreach ($request->items as $item) {
            $producto = DB::table('producto_sucursal')
                ->where('sucursal_id', $sucursalId)
                ->where('producto_id', $item['id'])
                ->first();

            if (!$producto) {
                return response()->json([
                    'error' => 'Producto no disponible en esta sucursal',
                    'mensaje' => "El producto con ID {$item['id']} no existe en la sucursal seleccionada."
                ], 422);
            }

            $stockDisponible = $producto->cantidad_fisica - $producto->cantidad_reservada;
            $cantidadSolicitada = (int) $item['cantidad'];

            if ($stockDisponible <= 0) {
                return response()->json([
                    'error' => 'Sin Stock',
                    'mensaje' => "El producto ya no está disponible."
                ], 422);
            }

            if ($cantidadSolicitada > $stockDisponible) {
                return response()->json([
                    'error' => 'Stock Insuficiente',
                    'mensaje' => "Solo quedan {$stockDisponible} unidades disponibles."
                ], 422);
            }
        }

        // Reservamos el stock para que otro pedido no lo tome mientras se procesa este
        foreach ($request->items as $item) {
            DB::table('producto_sucursal')
                ->where('sucursal_id', $sucursalId)
                ->where('producto_id', $item['id'])
                ->increment('cantidad_reservada', (int) $item['cantidad']);
        }

        // Iniciamos una transacción: si algo falla en el medio (ej: falla MP), no se guarda el pedido en la BD
        DB::beginTransaction();
        
        try {
            $comercio = Comercio::findOrFail($request->comercio_id);

            // 2. GUARDAR EL PEDIDO (PADRE)
            $pedido = PedidoWeb::create([
                'comercio_id'      => $comercio->id,
                'sucursal_id'      => $request->sucursal_id,
                'cliente_nombre'   => $nombreCliente,
                'cliente_telefono' => $request->telefono_contacto,
                'cliente_direccion' => $request->tipo_entrega === 'delivery' 
                    ? trim($request->direccion_entrega . ' ' . ($request->piso_depto ?? ''))
                    : 'Retiro en local',
                'subtotal'       => $request->total_productos,
                'costo_envio'    => $request->costo_envio ?? 0,
                'total'          => $request->total_final,
                'metodo_pago'    => $request->metodo_pago,
                'estado_pago'    => 'pendiente',
                'estado_pedido'  => 'nuevo',
                'notas'          => $request->notas,
            ]);

            // 3. GUARDAR LOS ÍTEMS Y PREPARAR ARRAY PARA MERCADO PAGO
            $itemsParaMercadoPago = [];

            foreach ($request->items as $item) {
                PedidoWebItem::create([
                    'pedido_web_id'   => $pedido->id,
                    'producto_id'     => $item['id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal'        => $item['precio'] * $item['cantidad'],
                ]);

                // Formato que exige la API de Mercado Pago
                $itemsParaMercadoPago[] = [
                    'title'       => $item['nombre'],
                    'quantity'    => (int) $item['cantidad'],
                    'unit_price'  => (float) $item['precio'],
                    'currency_id' => 'ARS'
                ];
            }

            // Si hay envío, MP lo toma como un ítem más
            if ($request->costo_envio > 0) {
                $itemsParaMercadoPago[] = [
                    'title'       => 'Costo de Envío (Delivery)',
                    'quantity'    => 1,
                    'unit_price'  => (float) $request->costo_envio,
                    'currency_id' => 'ARS'
                ];
            }

            // ============================================================
            // 4. LÓGICA DE PAGO
            // ============================================================
            
            // CASO A: MERCADO PAGO
            if ($request->metodo_pago === 'mercadopago') {
                
                if (!$comercio->mp_access_token) {
                    throw new \Exception('El local no tiene configurado Mercado Pago.');
                }

                $backUrlBase = route('tienda.pedido.confirmacion', [
                    'slug'   => $comercio->slug ?? 'default',
                    'pedido' => $pedido->id,
                ]);

                $response = Http::withToken($comercio->mp_access_token)
                    ->post('https://api.mercadopago.com/checkout/preferences', [
                    'items' => $itemsParaMercadoPago,
                    'external_reference' => (string) $pedido->id,
                    'back_urls' => [
                        'success' => $backUrlBase . '?status=approved',
                        'pending' => $backUrlBase . '?status=pending',
                        'failure' => $backUrlBase . '?status=rejected',
                    ],
                    'auto_return' => 'approved',
                    'notification_url' => url('/api/mercadopago/notificacion?comercio_id=' . $comercio->id),
                    'binary_mode' => true,
                ]);

                if ($response->successful()) {
                    DB::commit(); // Todo bien, guardamos en BD
                    return response()->json([
                        'url_pago' => $response->json('init_point')
                    ]);
                } else {
                    // Si MP falla, lanzamos excepción para hacer el Rollback de la BD
                    $detalleError = $response->json();
                    throw new \Exception('Error MP: ' . json_encode($detalleError));
                }
            }

            // CASO B: EFECTIVO / TRANSFERENCIA
            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack(); // Si algo falló, borramos el intento de pedido de la BD
            // Liberamos el stock reservado si el pedido no se concretó
            foreach ($request->items as $item) {
                DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $item['id'])
                    ->decrement('cantidad_reservada', (int) $item['cantidad']);
            }
            return response()->json([
                'error' => 'Error al procesar el pedido',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}