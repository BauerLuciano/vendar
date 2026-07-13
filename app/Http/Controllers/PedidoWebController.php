<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use App\Models\PedidoWebItem;
use App\Models\Producto;
use App\Models\Comercio;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentRecorder;
use App\Services\Payment\Contracts\CheckoutRequest;

class PedidoWebController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRecorder $paymentRecorder,
    ) {}

    public function store(Request $request)
    {
        $gatewayProviders = $this->paymentService->getRegisteredProviders();
        $metodosPagoValidos = array_merge(['efectivo', 'transferencia'], $gatewayProviders);

        $request->validate([
            'comercio_id' => 'required|exists:comercios,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:productos,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'tipo_entrega' => 'required|in:local,delivery',
            'telefono_contacto' => 'required_if:tipo_entrega,delivery|nullable|string|min:6',
            'metodo_pago' => 'required|in:' . implode(',', $metodosPagoValidos),
            'direccion_entrega' => 'required_if:tipo_entrega,delivery|nullable|string',
        ]);

        $user = $request->user();

        $nombreCliente = 'Cliente Invitado';
        if ($user) {
            $nombreCliente = $user->name ?? ($user->nombre . ' ' . $user->apellido);
        } elseif ($request->cliente_nombre) {
            $nombreCliente = $request->cliente_nombre;
        }

        $sucursalId = (int) $request->sucursal_id;
        $comercioId = (int) $request->comercio_id;

        $sucursalValida = Sucursal::where('id', $sucursalId)
            ->where('comercio_id', $comercioId)
            ->exists();

        if (!$sucursalValida) {
            return response()->json([
                'error' => 'La sucursal seleccionada no pertenece a este comercio.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $productosIds = array_column($request->items, 'id');
            $productos = Producto::whereIn('id', $productosIds)
                ->where('estado', true)
                ->with('reglaLiquidacion')
                ->get()
                ->keyBy('id');

            $itemsCalculados = [];
            $subtotalProductos = 0;

            foreach ($request->items as $item) {
                $productoId = (int) $item['id'];
                $cantidad = (int) $item['cantidad'];

                $producto = $productos->get($productoId);

                if (!$producto) {
                    throw new \Exception("El producto con ID {$productoId} no está activo o no existe.");
                }

                $productoStock = DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $productoId)
                    ->lockForUpdate()
                    ->first();

                if (!$productoStock) {
                    throw new \Exception("El producto \"{$producto->nombre}\" no está disponible en la sucursal seleccionada.");
                }

                $stockDisponible = $productoStock->cantidad_fisica - $productoStock->cantidad_reservada;

                if ($stockDisponible <= 0) {
                    throw new \Exception("El producto \"{$producto->nombre}\" ya no está disponible.");
                }

                if ($cantidad > $stockDisponible) {
                    throw new \Exception("Solo quedan {$stockDisponible} unidades de \"{$producto->nombre}\".");
                }

                DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $productoId)
                    ->increment('cantidad_reservada', $cantidad);

                $precioUnitario = (float) $producto->precio_venta;

                $enLiquidacion = false;
                $porcentajeDescuento = 0;

                if ($producto->reglaLiquidacion && $producto->reglaLiquidacion->estado) {
                    $tieneLotes = DB::table('lotes')
                        ->where('producto_id', $productoId)
                        ->where('sucursal_id', $sucursalId)
                        ->where('estado_liquidacion', true)
                        ->where('stock_actual', '>', 0)
                        ->exists();

                    if ($tieneLotes) {
                        $enLiquidacion = true;
                        $porcentajeDescuento = (float) $producto->reglaLiquidacion->porcentaje_descuento;
                        $descuento = $precioUnitario * ($porcentajeDescuento / 100);
                        $precioUnitario = round($precioUnitario - $descuento, 2);
                    }
                }

                $subtotalItem = round($precioUnitario * $cantidad, 2);
                $subtotalProductos += $subtotalItem;

                $itemsCalculados[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotalItem,
                    'en_liquidacion' => $enLiquidacion,
                ];
            }

            $comercio = Comercio::findOrFail($comercioId);

            $costoEnvio = 0;
            if ($request->tipo_entrega === 'delivery') {
                $precioBase = (float) ($comercio->envio_precio_base ?? 0);
                $precioPorKm = (float) ($comercio->envio_precio_km ?? 0);
                $distanciaKm = (float) ($request->distancia_km ?? 0);

                if ($distanciaKm > 0) {
                    $costoEnvio = round($precioBase + ($distanciaKm * $precioPorKm), 2);
                } else {
                    $costoEnvio = $precioBase;
                }
            }

            $totalFinal = round($subtotalProductos + $costoEnvio, 2);

            $pedido = new PedidoWeb();
            $pedido->comercio_id = $comercio->id;
            $pedido->sucursal_id = $sucursalId;
            $consumidor = auth('consumidor')->user();
            $pedido->consumidor_id = $consumidor?->id;
            $pedido->tipo_entrega = $request->tipo_entrega;
            $pedido->cliente_nombre = $nombreCliente;
            $pedido->cliente_telefono = $request->telefono_contacto;
            $pedido->cliente_direccion = $request->tipo_entrega === 'delivery'
                ? trim(($request->direccion_entrega ?? '') . ' ' . ($request->piso_depto ?? ''))
                : 'Retiro en local';
            $pedido->subtotal = $subtotalProductos;
            $pedido->costo_envio = $costoEnvio;
            $pedido->total = $totalFinal;
            $pedido->metodo_pago = $request->metodo_pago;
            $pedido->estado_pago = 'pendiente';
            $pedido->estado_pedido = 'nuevo';
            $pedido->notas = $request->notas;
            $pedido->save();

            $itemsParaPasarela = [];

            foreach ($itemsCalculados as $itemCalc) {
                PedidoWebItem::create([
                    'pedido_web_id'   => $pedido->id,
                    'producto_id'     => $itemCalc['producto']->id,
                    'cantidad'        => $itemCalc['cantidad'],
                    'precio_unitario' => $itemCalc['precio_unitario'],
                    'subtotal'        => $itemCalc['subtotal'],
                ]);

                $itemsParaPasarela[] = [
                    'title'       => $itemCalc['producto']->nombre,
                    'quantity'    => $itemCalc['cantidad'],
                    'unit_price'  => $itemCalc['precio_unitario'],
                    'currency_id' => 'ARS',
                ];
            }

            if ($costoEnvio > 0) {
                $itemsParaPasarela[] = [
                    'title'       => 'Costo de Envío (Delivery)',
                    'quantity'    => 1,
                    'unit_price'  => $costoEnvio,
                    'currency_id' => 'ARS',
                ];
            }

            if ($this->paymentService->isGatewayProvider($request->metodo_pago)) {
                $backUrlBase = route('tienda.pedido.confirmacion', [
                    'slug'   => $comercio->slug ?? 'default',
                    'pedido' => $pedido->id,
                ]);

                $gateway = $this->paymentService
                    ->forCommerce($comercio)
                    ->gateway($request->metodo_pago);

                $checkoutRequest = new CheckoutRequest(
                    referenceId: (string) $pedido->id,
                    amount: $totalFinal,
                    title: 'Pedido #' . $pedido->id,
                    description: 'Pedido en ' . ($comercio->nombre ?? 'VendAR'),
                    items: $itemsParaPasarela,
                    successUrl: $backUrlBase . '?status=approved',
                    failureUrl: $backUrlBase . '?status=rejected',
                    pendingUrl: $backUrlBase . '?status=pending',
                    notificationUrl: $gateway->getWebhookUrl($comercio),
                );

                try {
                    $response = $this->paymentService
                        ->forCommerce($comercio)
                        ->createCheckout($request->metodo_pago, $checkoutRequest);

                    $pedido->pasarela_payment_id = $response->gatewayTransactionId;
                    $pedido->save();

                    $this->paymentRecorder->recordCheckout(
                        $pedido,
                        $request->metodo_pago,
                        $checkoutRequest,
                        $response,
                    );

                    DB::commit();
                    return response()->json([
                        'url_pago' => $response->checkoutUrl,
                    ]);
                } catch (\Throwable $e) {
                    throw new \Exception('Error al procesar el pago: ' . $e->getMessage());
                }
            }

            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::warning('PedidoWeb: Error al crear pedido', [
                'error' => $e->getMessage(),
                'comercio_id' => $comercioId ?? null,
                'sucursal_id' => $sucursalId ?? null,
            ]);
            return response()->json([
                'error' => 'Error al procesar el pedido',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
}
