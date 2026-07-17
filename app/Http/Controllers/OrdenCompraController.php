<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Sucursal;
use App\Models\Proveedor;
use App\Models\IngresoMercaderia;
use App\Models\IngresoDetalle;
use App\Models\Producto;
use App\Services\LoteService;
use App\Services\SucursalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrdenConfirmadaProveedor; 
use Barryvdh\DomPDF\Facade\Pdf;

class OrdenCompraController extends Controller
{
    public function __construct(private SucursalScopeService $scope) {}

    public function index(Request $request)
    {
        $esJefe = $this->scope->esJefe();
        
        $search = $request->input('search');
        $estado = $request->input('estado', 'all');
        $proveedor_id = $request->input('proveedor_id', 'all');
        $fecha_desde = $request->input('fecha_desde');
        $fecha_hasta = $request->input('fecha_hasta');

        $query = OrdenCompra::with(['proveedor', 'sucursal', 'usuario', 'detalles.producto']);

        if (!$esJefe) {
            $sucursalId = $this->scope->obtenerSucursalActiva();
            if ($sucursalId) {
                $query->where('sucursal_id', $sucursalId);
            }
        }

        $ordenes = $query->when($search, function ($q, $search) {
                if (is_numeric($search)) {
                    $q->where('id', $search);
                }
            })
            ->when($estado !== 'all', function ($q) use ($estado) {
                $q->where('estado', $estado);
            })
            ->when($proveedor_id !== 'all', function ($q) use ($proveedor_id) {
                $q->where('proveedor_id', $proveedor_id);
            })
            ->when($fecha_desde, function ($q, $fecha_desde) {
                $q->whereDate('fecha_emision', '>=', $fecha_desde);
            })
            ->when($fecha_hasta, function ($q, $fecha_hasta) {
                $q->whereDate('fecha_emision', '<=', $fecha_hasta);
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $comercioId = $this->scope->obtenerComercioId();
        $proveedores = Proveedor::deComercio($comercioId)->where('estado', true)->get();
        $sucursales = $esJefe
            ? Sucursal::all()
            : Sucursal::whereIn('id', array_filter([$this->scope->obtenerSucursalActiva()]))->get();

        return Inertia::render('OrdenesCompra/Index', [
            'ordenes' => $ordenes,
            'proveedores' => $proveedores,
            'sucursales' => $sucursales,
            'filtros' => $request->only(['search', 'estado', 'proveedor_id', 'fecha_desde', 'fecha_hasta'])
        ]);
    }

    public function generarSugerencias()
    {
        $esJefe = $this->scope->esJefe();
        $sucursalesToProcess = $esJefe
            ? $this->scope->obtenerSucursalesDelComercioIds()
            : array_filter([$this->scope->obtenerSucursalActiva()]);

        DB::beginTransaction();
        try {
            foreach ($sucursalesToProcess as $sucId) {
                $productosBajoStock = DB::table('productos')
                    ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
                    ->where('producto_sucursal.sucursal_id', $sucId)
                    ->where('productos.estado', true)
                    ->whereNotNull('productos.proveedor_id')
                    ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo')
                    ->select('productos.id', 'productos.proveedor_id', 'productos.stock_minimo', 'productos.precio_costo', 'producto_sucursal.cantidad_fisica')
                    ->get();

                $porProveedor = $productosBajoStock->groupBy('proveedor_id');

                foreach ($porProveedor as $proveedorId => $productos) {
                    $orden = OrdenCompra::firstOrCreate(
                        ['sucursal_id' => $sucId, 'proveedor_id' => $proveedorId, 'estado' => 'Sugerida'],
                        ['user_id' => $user->id, 'fecha_emision' => now(), 'total_estimado' => 0, 'observaciones' => 'Generada automáticamente por alerta de stock mínimo.']
                    );

                    $totalEstimado = $orden->total_estimado;

                    foreach ($productos as $prod) {
                        $detalleExiste = OrdenCompraDetalle::where('orden_compra_id', $orden->id)->where('producto_id', $prod->id)->exists();
                        if (!$detalleExiste) {
                            $cantidadPedida = ($prod->stock_minimo * 2) - $prod->cantidad_fisica;
                            if ($cantidadPedida <= 0) $cantidadPedida = 1;
                            $costo = $prod->precio_costo ?? 0; 
                            $subtotal = $cantidadPedida * $costo;

                            OrdenCompraDetalle::create([
                                'orden_compra_id' => $orden->id,
                                'producto_id' => $prod->id,
                                'cantidad_pedida' => $cantidadPedida,
                                'costo_unitario_estimado' => $costo,
                                'subtotal_estimado' => $subtotal
                            ]);
                            $totalEstimado += $subtotal;
                        }
                    }
                    $orden->update(['total_estimado' => $totalEstimado]);
                }
            }
            DB::commit();
            return redirect()->back()->with('exito', 'Órdenes sugeridas generadas.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function confirmarPedido(OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if ($ordenCompra->estado !== 'Cotizada') {
            return redirect()->back()->with('error', 'Solo se pueden confirmar órdenes cotizadas.');
        }

        $ordenCompra->update(['estado' => 'Aprobada']);

        $correoDestino = $ordenCompra->proveedor->email ?? 'proveedor@test.com';
        Mail::to($correoDestino)->send(new OrdenConfirmadaProveedor($ordenCompra));

        return redirect()->back()->with('exito', '¡Pedido confirmado! Se le envió un correo al proveedor.');
    }

    public function aprobarYRecibir(OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if ($ordenCompra->estado !== 'Aprobada') {
            return redirect()->back()->with('error', 'Primero debes confirmar el pedido al proveedor.');
        }

        DB::beginTransaction();
        try {
            $ordenCompra->load('detalles.producto');

            $ingreso = IngresoMercaderia::create([
                'sucursal_id'  => $ordenCompra->sucursal_id,
                'proveedor_id' => $ordenCompra->proveedor_id,
                'user_id'      => auth()->id(),
                'fecha_ingreso' => now(),
                'numero_remito' => 'AUTO-OC-' . str_pad($ordenCompra->id, 4, '0', STR_PAD_LEFT),
                'total_costo'   => $ordenCompra->total_estimado,
            ]);

            $alertasInflacion = [];
            $loteService = app(LoteService::class);

            foreach ($ordenCompra->detalles as $detalle) {
                IngresoDetalle::create([
                    'ingreso_mercaderia_id' => $ingreso->id,
                    'producto_id'           => $detalle->producto_id,
                    'cantidad_recibida'     => $detalle->cantidad_pedida,
                    'costo_unitario'        => $detalle->costo_unitario_estimado,
                    'fecha_vencimiento'     => $detalle->fecha_vencimiento,
                ]);

                if ($detalle->fecha_vencimiento) {
                    $loteService->upsert(
                        (int) $detalle->producto_id,
                        (int) $ordenCompra->sucursal_id,
                        $detalle->fecha_vencimiento,
                        (float) $detalle->cantidad_pedida
                    );
                }

                $producto = $detalle->producto;
                $costoAnterior = $producto->precio_costo;
                $costoNuevo = $detalle->costo_unitario_estimado;
                $precioVentaAnterior = $producto->precio_venta;

                $stockLocked = DB::table('producto_sucursal')
                    ->where('producto_id', $detalle->producto_id)
                    ->where('sucursal_id', $ordenCompra->sucursal_id)
                    ->lockForUpdate()
                    ->first();

                $cantidadAnterior = $stockLocked ? (float) $stockLocked->cantidad_fisica : 0;
                $stockTotal = $cantidadAnterior + $detalle->cantidad_pedida;

                // PPP: Precio de Promedio Ponderado
                $nuevoPPP = $stockTotal > 0
                    ? round(($cantidadAnterior * $costoAnterior + $detalle->cantidad_pedida * $costoNuevo) / $stockTotal, 2)
                    : $costoNuevo;

                // Alerta de inflación (costo de compra vs costo anterior)
                if ($costoNuevo > $costoAnterior && $costoAnterior > 0) {
                    $alertasInflacion[] = [
                        'producto'    => $producto->nombre,
                        'costo_viejo' => $costoAnterior,
                        'costo_nuevo' => $costoNuevo,
                        'porcentaje'  => number_format((($costoNuevo - $costoAnterior) / $costoAnterior) * 100, 2),
                    ];
                }

                // Actualizar producto si el PPP cambió
                if ($nuevoPPP != $costoAnterior) {
                    $producto->update(['precio_costo' => $nuevoPPP]);

                    DB::table('historico_costos')->insert([
                        'producto_id'           => $detalle->producto_id,
                        'costo_anterior'        => $costoAnterior,
                        'costo_nuevo'           => $nuevoPPP,
                        'precio_venta_anterior' => $precioVentaAnterior,
                        'precio_venta_nuevo'    => $precioVentaAnterior,
                        'user_id'               => auth()->id(),
                        'origen_tipo'           => 'Recepción OC',
                        'origen_id'             => $ordenCompra->id,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }

                DB::table('producto_sucursal')
                    ->where('producto_id', $detalle->producto_id)
                    ->where('sucursal_id', $ordenCompra->sucursal_id)
                    ->update([
                        'cantidad_fisica' => DB::raw("cantidad_fisica + " . (float) $detalle->cantidad_pedida),
                        'updated_at'     => now(),
                    ]);

                DB::table('movimientos_stock')->insert([
                    'producto_id'         => $detalle->producto_id,
                    'sucursal_id'         => $ordenCompra->sucursal_id,
                    'user_id'             => auth()->id(),
                    'tipo_movimiento'     => 'Ingreso OC',
                    'cantidad_anterior'   => $cantidadAnterior,
                    'cantidad_movimiento' => $detalle->cantidad_pedida,
                    'cantidad_actual'     => $cantidadAnterior + $detalle->cantidad_pedida,
                    'motivo'              => "OC #{$ordenCompra->id}",
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $ordenCompra->update(['estado' => 'Recepcionada']);
            DB::commit();

            return redirect()->route('ingresos.index')->with([
                'exito' => 'Mercadería recibida. Precios y Stock actualizados.',
                'alertas_inflacion' => $alertasInflacion
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function cambiarEstado(Request $request, OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        $validated = $request->validate([
            'estado' => 'required|in:Sugerida,Borrador,Enviada,Cotizada,Aprobada,Recepcionada,Cancelada'
        ]);
        $ordenCompra->update(['estado' => $validated['estado']]);
        return redirect()->back()->with('exito', "Estado actualizado.");
    }

    public function destroy(OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if (in_array($ordenCompra->estado, ['Enviada', 'Recepcionada', 'Cotizada', 'Aprobada'])) {
            return redirect()->back()->with('error', 'No se puede eliminar una orden con actividad.');
        }
        $ordenCompra->delete();
        return redirect()->back()->with('exito', 'Orden eliminada.');
    }
    public function descargarPDF(OrdenCompra $ordenCompra)
        {
            if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
                abort(403, 'Esta orden no pertenece a tu comercio.');
            }

            $ordenCompra->load(['proveedor', 'sucursal', 'usuario', 'detalles.producto']);
            $config = \App\Models\Configuracion::pluck('valor', 'clave')->toArray();

            $logoBase64 = null;
            if (!empty($config['logo_empresa'])) {
                $pathLogo = storage_path('app/public/' . $config['logo_empresa']);
                if (file_exists($pathLogo) && is_file($pathLogo)) {
                    $logoBase64 = 'data:image/' . pathinfo($pathLogo, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($pathLogo));
                }
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orden-compra', [
                'orden'   => $ordenCompra,
                'config'  => $config,
                'logo'    => $logoBase64,
                'usuario' => auth()->user()->name,
                'fecha'   => now()->format('d/m/Y'),
                'hora'    => now()->format('H:i')
            ]);

            // IMPORTANTE: Forzar A4 y habilitar PHP si fuera necesario
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download('Orden_Compra_' . $ordenCompra->id . '.pdf');
        }
}