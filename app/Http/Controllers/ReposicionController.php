<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\ReposicionSugerida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\PreOrdenProveedor;
use Illuminate\Support\Str;

class ReposicionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $sucursalId = session('sucursal_activa_id', $user->branch_id);
        if (!$sucursalId) {
            return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
        }

        $comercioId = $user->branch?->comercio_id;

        // 1. Stock físico REAL como única fuente de verdad (recálculo en vivo)
        $productos = DB::table('producto_sucursal')
            ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->where('producto_sucursal.sucursal_id', $sucursalId)
            ->select(
                'productos.id',
                'productos.nombre',
                'productos.codigo_barras',
                'productos.unidad_medida',
                'productos.stock_minimo',
                'productos.stock_objetivo',
                'productos.precio_costo',
                'productos.proveedor_id',
                'producto_sucursal.cantidad_fisica'
            )
            ->get();

        // 2. Ventas de los últimos 30 días (para desempatar)
        $ventas30 = DB::table('detalle_ventas')
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->join('turno_cajas', 'turno_cajas.id', '=', 'ventas.turno_caja_id')
            ->where('turno_cajas.sucursal_id', $sucursalId)
            ->where('ventas.estado', 'Completada')
            ->where('ventas.created_at', '>=', now()->subDays(30))
            ->groupBy('detalle_ventas.producto_id')
            ->select('detalle_ventas.producto_id', DB::raw('SUM(detalle_ventas.cantidad) as total'))
            ->pluck('total', 'producto_id');

        // 3. Productos ocultos por "Recordar mañana" (solo estado, nunca cantidades)
        $ignorados = ReposicionSugerida::where('comercio_id', $comercioId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'ignorado')
            ->where('ignorado_hasta', '>', now())
            ->pluck('id', 'producto_id');

        // 4. Cálculo en vivo
        //    stock_minimo  → ¿cuándo debo comprar? (aparece si stock < mínimo)
        //    stock_objetivo → ¿hasta cuánto debo comprar? (cantidad = objetivo − stock)
        $faltantes = collect();
        $sinMinimo = 0;
        $sinObjetivo = 0;

        foreach ($productos as $p) {
            $minimo = (float) $p->stock_minimo;
            $objetivo = (float) $p->stock_objetivo;

            if ($minimo <= 0) {
                $sinMinimo++;
                continue;
            }

            if ($objetivo <= 0) {
                $sinObjetivo++;
                continue;
            }

            if ((float) $p->cantidad_fisica >= $minimo) {
                continue;
            }

            if ($ignorados->has($p->id)) {
                continue;
            }

            $cantidad = $objetivo - (float) $p->cantidad_fisica;
            $cantidadSugerida = $this->redondearSugerido($cantidad, $p->unidad_medida);

            $faltantes->push((object) [
                'id'                => $p->id,
                'nombre'            => $p->nombre,
                'codigo_barras'     => $p->codigo_barras,
                'unidad_medida'     => $p->unidad_medida,
                'cantidad_fisica'   => (int) $p->cantidad_fisica,
                'stock_minimo'      => $minimo,
                'stock_objetivo'    => $objetivo,
                'cantidad_sugerida' => $cantidadSugerida,
                'criticidad'        => $minimo > 0 ? (float) $p->cantidad_fisica / $minimo : 1.0,
                'ventas_30d'        => (float) ($ventas30[$p->id] ?? 0),
                'precio_costo'      => (float) $p->precio_costo,
                'costo_estimado'    => round($cantidadSugerida * (float) $p->precio_costo, 2),
            ]);
        }

        // 5. Priorización: criticidad (menor primero) y desempate por ventas
        $faltantes = $faltantes->sort(function ($a, $b) {
            if ($a->criticidad !== $b->criticidad) {
                return $a->criticidad <=> $b->criticidad;
            }
            return $b->ventas_30d <=> $a->ventas_30d;
        })->values();

        // 6. Resumen económico
        $resumen = [
            'total_productos' => $faltantes->count(),
            'costo_estimado'  => round($faltantes->sum(fn ($f) => $f->costo_estimado), 2),
            'agotados'        => $faltantes->where('cantidad_fisica', '<=', 0)->count(),
            'criticos'        => $faltantes->where('cantidad_fisica', '>', 0)->count(),
        ];

        $todos = $request->boolean('todos');
        $mostrados = $todos ? $faltantes : $faltantes->take(10);

        return Inertia::render('Reposicion/Index', [
            'faltantes'       => $mostrados->values(),
            'resumen'         => $resumen,
            'sin_minimo'      => $sinMinimo,
            'sin_objetivo'    => $sinObjetivo,
            'todos'           => $todos,
            'sucursalActual'  => Sucursal::find($sucursalId),
        ]);
    }

    public function recordar(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
        ]);

        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $sucursalId = session('sucursal_activa_id', $user->branch_id);

        if (!$sucursalId) {
            return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
        }

        ReposicionSugerida::updateOrCreate(
            [
                'comercio_id' => $comercioId,
                'sucursal_id' => $sucursalId,
                'producto_id' => $request->producto_id,
            ],
            [
                'estado'        => 'ignorado',
                'ignorado_hasta' => now()->endOfDay(),
            ]
        );

        return redirect()->back()->with('success', 'Producto ocultado por hoy. Reaparecerá mañana si sigue faltando.');
    }

    public function generarPreOrdenes(Request $request)
    {
        $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.proveedor_id' => 'required|exists:proveedores,id',
            'productos.*.cantidad' => 'required|integer|min:1',
            'productos.*.precio_costo' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        $sucursalId = session('sucursal_activa_id', $user->branch_id);
        if (!$sucursalId) {
            return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
        }

        DB::beginTransaction();

        try {
            $porProveedor = collect($request->productos)->groupBy('proveedor_id');

            foreach ($porProveedor as $proveedorId => $items) {
                $orden = OrdenCompra::create([
                    'sucursal_id' => $sucursalId,
                    'proveedor_id' => $proveedorId,
                    'user_id' => $user->id,
                    'estado' => 'Borrador', 
                    'token_cotizacion' => Str::random(40),
                    'fecha_emision' => now(),
                    'total_estimado' => 0,
                    'observaciones' => 'Solicitud de reposición inteligente.',
                ]);

                $total = 0;

                foreach ($items as $item) {
                    $subtotal = $item['cantidad'] * $item['precio_costo'];
                    
                    OrdenCompraDetalle::create([
                        'orden_compra_id' => $orden->id,
                        'producto_id' => $item['producto_id'],
                        'cantidad_pedida' => $item['cantidad'],
                        'costo_unitario_estimado' => $item['precio_costo'],
                        'subtotal_estimado' => $subtotal
                    ]);

                    $total += $subtotal;
                }
                $correoDestino = $orden->proveedor->email ?? 'proveedor@test.com';
                Mail::to($correoDestino)->send(new PreOrdenProveedor($orden));
                $orden->update(['total_estimado' => $total]);
            }

            DB::commit();
            
            return redirect()->back()->with('success', '¡Pre-Órdenes generadas y correos enviados exitosamente!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Hubo un error: ' . $e->getMessage());
        }
    }

    public function verCotizacion(Request $request, $id)
    {
        $orden = OrdenCompra::with(['detalles.producto', 'proveedor', 'sucursal'])->findOrFail($id);
        if (!$orden->token_cotizacion || $request->token !== $orden->token_cotizacion) {
            abort(403, 'Este enlace de cotización es inválido, falso o ya caducó.');
        }

        return Inertia::render('Reposicion/Cotizar', [
            'orden' => $orden
        ]);
    }

    public function guardarCotizacion(Request $request, $id)
    {
        $request->validate([
            'fecha_entrega' => 'required|date',
            'detalles' => 'required|array',
            'detalles.*.cantidad_pedida' => 'required|numeric|min:0',
            'detalles.*.costo_unitario_estimado' => 'required|numeric|min:0',
        ]);

        $orden = OrdenCompra::findOrFail($id);

        if (!$orden->token_cotizacion || $request->token !== $orden->token_cotizacion) {
            abort(403, 'Enlace inválido.');
        }

        $totalEstimado = 0;

        foreach ($request->detalles as $item) {
            $subtotal = $item['cantidad_pedida'] * $item['costo_unitario_estimado'];
            
            OrdenCompraDetalle::where('id', $item['id'])->update([
                'cantidad_pedida' => $item['cantidad_pedida'],
                'costo_unitario_estimado' => $item['costo_unitario_estimado'],
                'subtotal_estimado' => $subtotal
            ]);

            $totalEstimado += $subtotal;
        }

        $orden->update([
            'estado' => 'Cotizada',
            'total_estimado' => $totalEstimado,
            'fecha_entrega_esperada' => $request->fecha_entrega,
        ]);

        return redirect()->back();
    }

    private function redondearSugerido(float $cantidad, ?string $unidad): float
    {
        $unidad = strtolower(trim((string) $unidad));

        if (in_array($unidad, ['unidad', 'unidades', 'caja', 'cajas', 'pack', 'packs'], true)) {
            return (int) ceil($cantidad);
        }

        return round($cantidad, 2);
    }
}