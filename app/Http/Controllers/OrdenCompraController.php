<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\OrdenCompraHistorial;
use App\Models\Sucursal;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\IngresoMercaderia;
use App\Models\IngresoDetalle;
use App\Services\LoteService;
use App\Services\SucursalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrdenCompraController extends Controller
{
    public function __construct(private SucursalScopeService $scope) {}

    // =========================================================================
    // ÍNDICE
    // =========================================================================

    public function index(Request $request)
    {
        $esJefe = $this->scope->esJefe();
        $comercioId = $this->scope->obtenerComercioId();

        $search = $request->input('search');
        $estado = $request->input('estado', 'all');
        $proveedor_id = $request->input('proveedor_id', 'all');
        $fecha_desde = $request->input('fecha_desde');
        $fecha_hasta = $request->input('fecha_hasta');

        $query = OrdenCompra::with(['proveedor', 'sucursal', 'usuario', 'detalles.producto', 'historial.usuario']);

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

        $proveedores = Proveedor::deComercio($comercioId)->where('estado', true)->get();
        $sucursales = $esJefe
            ? Sucursal::all()
            : Sucursal::whereIn('id', array_filter([$this->scope->obtenerSucursalActiva()]))->get();
        $productos = Producto::where('estado', true)->get();

        return Inertia::render('OrdenesCompra/Index', [
            'ordenes'    => $ordenes,
            'proveedores' => $proveedores,
            'sucursales'  => $sucursales,
            'productos'   => $productos,
            'filtros'     => $request->only(['search', 'estado', 'proveedor_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    // =========================================================================
    // CREAR ORDEN MANUAL
    // =========================================================================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id'              => 'required|exists:proveedores,id',
            'sucursal_id'               => 'required|exists:sucursales,id',
            'fecha_entrega_esperada'    => 'nullable|date',
            'observaciones'             => 'nullable|string|max:1000',
            'items'                     => 'required|array|min:1',
            'items.*.producto_id'       => 'required|exists:productos,id',
            'items.*.cantidad_pedida'   => 'required|integer|min:1',
            'items.*.costo_unitario'    => 'required|numeric|min:0',
            'items.*.fecha_vencimiento' => 'nullable|date',
        ]);

        if (!$this->scope->puedeAccederSucursal((int) $validated['sucursal_id'])) {
            return redirect()->back()->withErrors(['sucursal_id' => 'No tenés acceso a esa sucursal.']);
        }

        $totalEstimado = 0;

        DB::beginTransaction();
        try {
            $orden = OrdenCompra::create([
                'proveedor_id'           => $validated['proveedor_id'],
                'sucursal_id'            => $validated['sucursal_id'],
                'user_id'                => auth()->id(),
                'fecha_emision'          => now(),
                'fecha_entrega_esperada' => $validated['fecha_entrega_esperada'] ?? null,
                'estado'                 => 'Sugerida',
                'total_estimado'         => 0,
                'observaciones'          => $validated['observaciones'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $subtotal = $item['cantidad_pedida'] * $item['costo_unitario'];
                $totalEstimado += $subtotal;

                OrdenCompraDetalle::create([
                    'orden_compra_id'          => $orden->id,
                    'producto_id'              => $item['producto_id'],
                    'cantidad_pedida'          => $item['cantidad_pedida'],
                    'cantidad_recibida'        => 0,
                    'costo_unitario_estimado'  => $item['costo_unitario'],
                    'subtotal_estimado'        => $subtotal,
                    'fecha_vencimiento'        => $item['fecha_vencimiento'] ?? null,
                ]);
            }

            $orden->update(['total_estimado' => $totalEstimado]);

            $this->registrarHistorial($orden->id, 'Sugerida', 'Orden creada manualmente.');

            DB::commit();
            return redirect()->back()->with('exito', 'Orden de compra creada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al crear la orden: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // EDITAR ORDEN (solo Sugerida)
    // =========================================================================

    public function update(Request $request, OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if ($ordenCompra->estado !== 'Sugerida') {
            return redirect()->back()->with('error', 'Solo se pueden editar órdenes en estado Sugerida.');
        }

        $validated = $request->validate([
            'proveedor_id'              => 'required|exists:proveedores,id',
            'sucursal_id'               => 'required|exists:sucursales,id',
            'fecha_entrega_esperada'    => 'nullable|date',
            'observaciones'             => 'nullable|string|max:1000',
            'items'                     => 'required|array|min:1',
            'items.*.producto_id'       => 'required|exists:productos,id',
            'items.*.cantidad_pedida'   => 'required|integer|min:1',
            'items.*.costo_unitario'    => 'required|numeric|min:0',
            'items.*.fecha_vencimiento' => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            $ordenCompra->update([
                'proveedor_id'           => $validated['proveedor_id'],
                'sucursal_id'            => $validated['sucursal_id'],
                'fecha_entrega_esperada' => $validated['fecha_entrega_esperada'] ?? null,
                'observaciones'          => $validated['observaciones'] ?? null,
            ]);

            $ordenCompra->detalles()->delete();

            $totalEstimado = 0;
            foreach ($validated['items'] as $item) {
                $subtotal = $item['cantidad_pedida'] * $item['costo_unitario'];
                $totalEstimado += $subtotal;

                OrdenCompraDetalle::create([
                    'orden_compra_id'          => $ordenCompra->id,
                    'producto_id'              => $item['producto_id'],
                    'cantidad_pedida'          => $item['cantidad_pedida'],
                    'cantidad_recibida'        => 0,
                    'costo_unitario_estimado'  => $item['costo_unitario'],
                    'subtotal_estimado'        => $subtotal,
                    'fecha_vencimiento'        => $item['fecha_vencimiento'] ?? null,
                ]);
            }

            $ordenCompra->update(['total_estimado' => $totalEstimado]);

            $this->registrarHistorial($ordenCompra->id, $ordenCompra->estado, 'Orden editada.');

            DB::commit();
            return redirect()->back()->with('exito', 'Orden actualizada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // ENVIAR AL PROVEEDOR
    // =========================================================================

    public function enviar(OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if (!in_array($ordenCompra->estado, ['Sugerida'])) {
            return redirect()->back()->with('error', 'Solo se pueden enviar órdenes en estado Sugerida.');
        }

        $ordenCompra->update([
            'estado'      => 'Enviada',
            'fecha_envio' => now(),
        ]);

        $this->registrarHistorial($ordenCompra->id, 'Enviada');

        return redirect()->back()->with('exito', 'Orden enviada al proveedor.');
    }

    // =========================================================================
    // CONFIRMAR (proveedor aceptó)
    // =========================================================================

    public function confirmar(OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if ($ordenCompra->estado !== 'Enviada') {
            return redirect()->back()->with('error', 'Solo se pueden confirmar órdenes en estado Enviada.');
        }

        $ordenCompra->update(['estado' => 'Confirmada']);

        $this->registrarHistorial($ordenCompra->id, 'Confirmada', 'Proveedor confirmó el pedido.');

        return redirect()->back()->with('exito', 'Orden confirmada por el proveedor.');
    }

    // =========================================================================
    // RECHAZAR (vuelve a Sugerida para editar)
    // =========================================================================

    public function rechazar(Request $request, OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if (!in_array($ordenCompra->estado, ['Enviada'])) {
            return redirect()->back()->with('error', 'Solo se pueden rechazar órdenes en estado Enviada.');
        }

        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $ordenCompra->update([
            'estado'      => 'Sugerida',
            'fecha_envio' => null,
        ]);

        $this->registrarHistorial(
            $ordenCompra->id,
            'Sugerida',
            'Rechazada: ' . $request->motivo_rechazo
        );

        return redirect()->back()->with('exito', 'Orden rechazada. Podés editarla y reenviarla.');
    }

    // =========================================================================
    // CANCELAR ORDEN
    // =========================================================================

    public function cancelar(Request $request, OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if (in_array($ordenCompra->estado, ['Recibida', 'Cancelada'])) {
            return redirect()->back()->with('error', 'No se puede cancelar una orden ya recibida o cancelada.');
        }

        $request->validate([
            'motivo_cancelacion' => 'required|string|max:500',
        ]);

        $ordenCompra->update(['estado' => 'Cancelada']);

        $this->registrarHistorial(
            $ordenCompra->id,
            'Cancelada',
            'Cancelada: ' . $request->motivo_cancelacion
        );

        return redirect()->back()->with('exito', 'Orden cancelada.');
    }

    // =========================================================================
    // RECIBIR (parcial o total)
    // =========================================================================

    public function recibir(Request $request, OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if (!in_array($ordenCompra->estado, ['Confirmada', 'Parcialmente Recibida'])) {
            return redirect()->back()->with('error', 'Solo se pueden recibir órdenes confirmadas o parcialmente recibidas.');
        }

        $request->validate([
            'items'                          => 'required|array|min:1',
            'items.*.orden_compra_detalle_id' => 'required|exists:orden_compra_detalles,id',
            'items.*.cantidad_recibir'        => 'required|numeric|min:0',
        ]);

        $ordenCompra->load('detalles.producto');
        $sucursalId = (int) $ordenCompra->sucursal_id;
        $detallesMap = $ordenCompra->detalles->keyBy('id');

        DB::beginTransaction();
        try {
            $alertasInflacion = [];
            $loteService = app(LoteService::class);
            $totalRecibido = 0;
            $hayPendientes = false;
            $itemsRecibidos = [];

            foreach ($request->items as $itemData) {
                $detalle = $detallesMap->get($itemData['orden_compra_detalle_id']);
                if (!$detalle) continue;

                $cantidadRecibir = (float) $itemData['cantidad_recibir'];
                if ($cantidadRecibir <= 0) {
                    $pendiente = $detalle->cantidad_pedida - $detalle->cantidad_recibida;
                    if ($pendiente > 0) $hayPendientes = true;
                    continue;
                }

                $pendiente = $detalle->cantidad_pedida - $detalle->cantidad_recibida;
                $cantidadRecibir = min($cantidadRecibir, $pendiente);

                $itemsRecibidos[] = [
                    'producto' => $detalle->producto->nombre ?? 'Producto #' . $detalle->producto_id,
                    'cantidad' => $cantidadRecibir,
                ];

                // --- LÓGICA DE STOCK (preservada de aprobarYRecibir) ---

                if ($detalle->fecha_vencimiento) {
                    $loteService->upsert(
                        (int) $detalle->producto_id,
                        $sucursalId,
                        $detalle->fecha_vencimiento->format('Y-m-d'),
                        $cantidadRecibir
                    );
                }

                $producto = $detalle->producto;
                $costoAnterior = $producto->precio_costo;
                $costoNuevo = $detalle->costo_unitario_estimado;
                $precioVentaAnterior = $producto->precio_venta;

                $stockLocked = DB::table('producto_sucursal')
                    ->where('producto_id', $detalle->producto_id)
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->first();

                $cantidadAnterior = $stockLocked ? (float) $stockLocked->cantidad_fisica : 0;
                $stockTotal = $cantidadAnterior + $cantidadRecibir;

                $nuevoPPP = $stockTotal > 0
                    ? round(($cantidadAnterior * $costoAnterior + $cantidadRecibir * $costoNuevo) / $stockTotal, 2)
                    : $costoNuevo;

                if ($costoNuevo > $costoAnterior && $costoAnterior > 0) {
                    $alertasInflacion[] = [
                        'producto'    => $producto->nombre,
                        'costo_viejo' => $costoAnterior,
                        'costo_nuevo' => $costoNuevo,
                        'porcentaje'  => number_format((($costoNuevo - $costoAnterior) / $costoAnterior) * 100, 2),
                    ];
                }

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
                    ->where('sucursal_id', $sucursalId)
                    ->update([
                        'cantidad_fisica' => DB::raw("cantidad_fisica + " . $cantidadRecibir),
                        'updated_at'     => now(),
                    ]);

                DB::table('movimientos_stock')->insert([
                    'producto_id'         => $detalle->producto_id,
                    'sucursal_id'         => $sucursalId,
                    'user_id'             => auth()->id(),
                    'tipo_movimiento'     => 'Ingreso OC',
                    'cantidad_anterior'   => $cantidadAnterior,
                    'cantidad_movimiento' => $cantidadRecibir,
                    'cantidad_actual'     => $cantidadAnterior + $cantidadRecibir,
                    'motivo'              => "OC #{$ordenCompra->id}" . ($cantidadRecibir < $detalle->cantidad_pedida ? " (parcial)" : ""),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // --- FIN LÓGICA DE STOCK ---

                $detalle->update([
                    'cantidad_recibida' => $detalle->cantidad_recibida + $cantidadRecibir,
                ]);

                $totalRecibido += $cantidadRecibir;

                $pendienteFinal = $detalle->cantidad_pedida - ($detalle->cantidad_recibida);
                if ($pendienteFinal > 0) $hayPendientes = true;
            }

            // Crear ingreso de mercadería si hubo recepción
            if ($totalRecibido > 0) {
                IngresoMercaderia::create([
                    'sucursal_id'   => $sucursalId,
                    'proveedor_id'  => $ordenCompra->proveedor_id,
                    'user_id'       => auth()->id(),
                    'fecha_ingreso' => now(),
                    'numero_remito' => 'OC-' . str_pad($ordenCompra->id, 4, '0', STR_PAD_LEFT),
                    'total_costo'   => $totalRecibido,
                ]);
            }

            // Determinar estado final
            $nuevoEstado = $hayPendientes ? 'Parcialmente Recibida' : 'Recibida';
            $ordenCompra->update(['estado' => $nuevoEstado]);

            $detalleHistorial = $hayPendientes
                ? 'Recepción parcial: ' . collect($itemsRecibidos)->map(fn($i) => "{$i['cantidad']}x {$i['producto']}")->implode(', ')
                : 'Recepción total de la orden.';

            $this->registrarHistorial($ordenCompra->id, $nuevoEstado, $detalleHistorial, [
                'items_recibidos' => $itemsRecibidos,
                'total_cantidad'  => $totalRecibido,
            ]);

            DB::commit();

            $mensaje = $hayPendientes
                ? "Recibido parcialmente. Quedan items pendientes."
                : "Orden recibida completamente. Stock y precios actualizados.";

            return redirect()->back()->with([
                'exito'           => $mensaje,
                'alertas_inflacion' => $alertasInflacion,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al recibir: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // GENERAR SUGERENCIAS AUTOMÁTICAS
    // =========================================================================

    public function generarSugerencias()
    {
        $esJefe = $this->scope->esJefe();
        $userId = $this->scope->usuario()->id;
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
                        ['user_id' => $userId, 'fecha_emision' => now(), 'total_estimado' => 0, 'observaciones' => 'Generada automáticamente por alerta de stock mínimo.']
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
                                'orden_compra_id'         => $orden->id,
                                'producto_id'             => $prod->id,
                                'cantidad_pedida'         => $cantidadPedida,
                                'cantidad_recibida'       => 0,
                                'costo_unitario_estimado' => $costo,
                                'subtotal_estimado'       => $subtotal,
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

    // =========================================================================
    // ELIMINAR (solo Sugerida)
    // =========================================================================

    public function destroy(OrdenCompra $ordenCompra)
    {
        if (!$this->scope->puedeAccederSucursal($ordenCompra->sucursal_id)) {
            abort(403, 'Esta orden no pertenece a tu comercio.');
        }

        if ($ordenCompra->estado !== 'Sugerida') {
            return redirect()->back()->with('error', 'Solo se pueden eliminar órdenes en estado Sugerida.');
        }

        $ordenCompra->delete();
        return redirect()->back()->with('exito', 'Orden eliminada.');
    }

    // =========================================================================
    // PDF
    // =========================================================================

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
            'hora'    => now()->format('H:i'),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('Orden_Compra_' . $ordenCompra->id . '.pdf');
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function registrarHistorial(
        int $ordenId,
        string $estado,
        ?string $motivo = null,
        ?array $detalle = null
    ): void {
        OrdenCompraHistorial::create([
            'orden_compra_id' => $ordenId,
            'estado'          => $estado,
            'user_id'         => auth()->id(),
            'motivo'          => $motivo,
            'detalle'         => $detalle,
        ]);
    }
}
