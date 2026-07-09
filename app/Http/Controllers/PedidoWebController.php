<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use App\Models\PedidoWebItem;
use App\Models\Comercio;
use Illuminate\Support\Facades\DB;
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
            'tipo_entrega' => 'required|in:local,delivery',
            'telefono_contacto' => 'required_if:tipo_entrega,delivery|nullable|string|min:6',
            'metodo_pago' => 'required|in:' . implode(',', $metodosPagoValidos),
            'direccion_entrega' => 'required_if:tipo_entrega,delivery|nullable|string',
            'total_productos' => 'required|numeric',
            'total_final' => 'required|numeric',
        ]);

        $user = $request->user();

        $nombreCliente = 'Cliente Invitado';
        if ($user) {
            $nombreCliente = $user->name ?? ($user->nombre . ' ' . $user->apellido);
        } elseif ($request->cliente_nombre) {
            $nombreCliente = $request->cliente_nombre;
        }

        $sucursalId = $request->sucursal_id;

        DB::beginTransaction();

        try {
            foreach ($request->items as $item) {
                $productoActivo = \App\Models\Producto::where('id', $item['id'])->where('estado', true)->exists();
                if (!$productoActivo) {
                    throw new \Exception("El producto con ID {$item['id']} no está activo.");
                }

                $productoStock = DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $item['id'])
                    ->lockForUpdate()
                    ->first();

                if (!$productoStock) {
                    throw new \Exception("El producto con ID {$item['id']} no existe en la sucursal seleccionada.");
                }

                $stockDisponible = $productoStock->cantidad_fisica - $productoStock->cantidad_reservada;
                $cantidadSolicitada = (int) $item['cantidad'];

                if ($stockDisponible <= 0) {
                    throw new \Exception('El producto ya no está disponible.');
                }

                if ($cantidadSolicitada > $stockDisponible) {
                    throw new \Exception("Solo quedan {$stockDisponible} unidades disponibles.");
                }

                DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $item['id'])
                    ->increment('cantidad_reservada', $cantidadSolicitada);
            }

            $comercio = Comercio::findOrFail($request->comercio_id);

            $pedido = new PedidoWeb();
            $pedido->comercio_id = $comercio->id;
            $pedido->sucursal_id = $request->sucursal_id;
            $pedido->tipo_entrega = $request->tipo_entrega;
            $pedido->cliente_nombre = $nombreCliente;
            $pedido->cliente_telefono = $request->telefono_contacto;
            $pedido->cliente_direccion = $request->tipo_entrega === 'delivery'
                ? trim($request->direccion_entrega . ' ' . ($request->piso_depto ?? ''))
                : 'Retiro en local';
            $pedido->subtotal = $request->total_productos;
            $pedido->costo_envio = $request->costo_envio ?? 0;
            $pedido->total = $request->total_final;
            $pedido->metodo_pago = $request->metodo_pago;
            $pedido->estado_pago = 'pendiente';
            $pedido->estado_pedido = 'nuevo';
            $pedido->notas = $request->notas;
            $pedido->save();

            $itemsParaPasarela = [];

            foreach ($request->items as $item) {
                PedidoWebItem::create([
                    'pedido_web_id'   => $pedido->id,
                    'producto_id'     => $item['id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal'        => $item['precio'] * $item['cantidad'],
                ]);

                $itemsParaPasarela[] = [
                    'title'       => $item['nombre'],
                    'quantity'    => (int) $item['cantidad'],
                    'unit_price'  => (float) $item['precio'],
                    'currency_id' => 'ARS'
                ];
            }

            if ($request->costo_envio > 0) {
                $itemsParaPasarela[] = [
                    'title'       => 'Costo de Envío (Delivery)',
                    'quantity'    => 1,
                    'unit_price'  => (float) $request->costo_envio,
                    'currency_id' => 'ARS'
                ];
            }

            // PASARELA DE PAGO
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
                    amount: (float) $request->total_final,
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
                        'url_pago' => $response->checkoutUrl
                    ]);
                } catch (\Throwable $e) {
                    throw new \Exception('Error al procesar el pago: ' . $e->getMessage());
                }
            }

            // EFECTIVO / TRANSFERENCIA
            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al procesar el pedido',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}
