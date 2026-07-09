<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Services\ProductLookupService;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Promotion\DTOs\PromotionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index()
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        return Inertia::render('Productos/Index', [
            'productos' => Producto::with(['categoria', 'marca', 'sucursales', 'proveedor'])
                ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->whereIn('sucursales.id', $sucursalIds)))
                ->orderBy('id', 'desc')
                ->get(),
            'categorias' => Categoria::deComercio($comercioId)->get(),
            'marcas' => Marca::deComercio($comercioId)->get(),
            'proveedores' => Proveedor::deComercio($comercioId)->where('estado', true)->get(),
            'sucursales' => $sucursalIds->isNotEmpty()
                ? Sucursal::whereIn('id', $sucursalIds)->get()
                : Sucursal::all(),
        ]);
    }

    public function store(Request $request, ProductLookupService $lookup)
    {
        $validados = $request->validate([
            'nombre'              => 'required|string|max:255',
            'codigo_barras'       => 'required|string|min:2|max:14|regex:/^[0-9]+$/|unique:productos,codigo_barras',
            'categoria_id'        => 'required|exists:categorias,id',
            'marca_id'            => 'required|exists:marcas,id',
            'proveedor_id'        => 'required|exists:proveedores,id',
            'unidad_medida'       => 'required|in:Unidad,Kg',
            'es_retornable'       => 'boolean',
            'precio_costo'        => 'required|numeric|min:0',
            'precio_venta'        => 'required|numeric|min:0',
            'stock_minimo'        => 'required|numeric|min:0',
            'stock_objetivo'     => 'nullable|integer|min:0',
            'stock_inicial'       => 'nullable|numeric|min:0',
            'descripcion'         => 'nullable|string',
            'imagen'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'imagen_url'          => 'nullable|url',
        ], [
            'codigo_barras.regex' => 'El código de barras solo puede contener números.',
            'codigo_barras.min' => 'El código debe tener al menos 2 números.',
            'codigo_barras.max' => 'El código no puede superar los 14 números.',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('imagen')) {
                $validados['imagen'] = $request->file('imagen')->store('productos', 'public');
            } elseif (!empty($validados['imagen_url'])) {
                try {
                    $imageContents = Http::get($validados['imagen_url'])->body();
                    if ($imageContents) {
                        $filename = 'productos/' . uniqid('prod_api_') . '.jpg';
                        Storage::disk('public')->put($filename, $imageContents);
                        $validados['imagen'] = $filename;
                    }
                } catch (\Exception $e) {
                    Log::warning("No se pudo descargar la imagen externa del producto: " . $e->getMessage());
                }
            }

            unset($validados['imagen_url']);

            $validados['estado'] = true;

            $producto = Producto::create($validados);

            $lookup->createFromManual([
                'codigo_barras' => $validados['codigo_barras'],
                'nombre' => $validados['nombre'],
                'descripcion' => $validados['descripcion'] ?? null,
                'imagen' => $validados['imagen'] ?? null,
            ]);

            $sucursalId = auth()->user()->branch_id;
            if (!$sucursalId) {
                throw new \Exception('No tenés una sucursal asignada para registrar productos.');
            }
            $cantidadInicial = $request->stock_inicial ?? 0;

            $producto->sucursales()->attach($sucursalId, [
                'cantidad_fisica' => $cantidadInicial,
                'cantidad_reservada' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($cantidadInicial > 0) {
                DB::table('movimientos_stock')->insert([
                    'producto_id' => $producto->id,
                    'sucursal_id' => $sucursalId,
                    'user_id' => auth()->id(),
                    'tipo_movimiento' => 'Stock Inicial',
                    'cantidad_anterior' => 0,
                    'cantidad_movimiento' => $cantidadInicial,
                    'cantidad_actual' => $cantidadInicial,
                    'motivo' => 'Carga inicial al registrar producto',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Producto registrado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al crear producto: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Producto $producto)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && !$producto->sucursales()->where('comercio_id', $comercioId)->exists()) {
            abort(403, 'Este producto no pertenece a tu comercio.');
        }

        $validados = $request->validate([
            'nombre'              => 'required|string|max:255',
            'codigo_barras'       => ['required', 'string', 'min:2', 'max:14', 'regex:/^[0-9]+$/', Rule::unique('productos')->ignore($producto->id)],
            'categoria_id'        => 'required|exists:categorias,id',
            'marca_id'            => 'required|exists:marcas,id',
            'proveedor_id'        => 'required|exists:proveedores,id',
            'unidad_medida'       => 'required|in:Unidad,Kg',
            'es_retornable'       => 'boolean',
            'precio_costo'        => 'required|numeric|min:0',
            'precio_venta'        => 'required|numeric|min:0',
            'stock_minimo'        => 'required|numeric|min:0',
            'stock_objetivo'     => 'nullable|integer|min:0',
            'descripcion'         => 'nullable|string',
            'imagen'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'imagen_url'          => 'nullable|url',
        ], [
            'codigo_barras.regex' => 'El código de barras solo puede contener números.',
        ]);

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $validados['imagen'] = $request->file('imagen')->store('productos', 'public');
        } elseif (!empty($validados['imagen_url']) && !$producto->imagen) {
            try {
                $imageContents = Http::get($validados['imagen_url'])->body();
                if ($imageContents) {
                    $filename = 'productos/' . uniqid('prod_api_') . '.jpg';
                    Storage::disk('public')->put($filename, $imageContents);
                    $validados['imagen'] = $filename;
                }
            } catch (\Exception $e) {
                Log::warning("No se pudo descargar la imagen externa: " . $e->getMessage());
            }
        } else {
            unset($validados['imagen']);
        }

        unset($validados['imagen_url']);

        $producto->update($validados);

        return redirect()->back()->with('success', 'Producto actualizado correctamente.');
    }

    public function status(Producto $producto)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && !$producto->sucursales()->where('comercio_id', $comercioId)->exists()) {
            abort(403, 'Este producto no pertenece a tu comercio.');
        }

        $producto->update(['estado' => !$producto->estado]);
        return redirect()->back()->with('success', 'Estado modificado.');
    }

    public function ajustarStock(Request $request, Producto $producto)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && !$producto->sucursales()->where('comercio_id', $comercioId)->exists()) {
            abort(403, 'Este producto no pertenece a tu comercio.');
        }

        $validados = $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'tipo_ajuste' => 'required|in:Sumar,Restar',
            'cantidad'    => 'required|numeric|min:0.001', 
            'motivo'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $sucursalPivot = $producto->sucursales()->where('sucursal_id', $validados['sucursal_id'])->first();

            $cantidadAnterior = $sucursalPivot ? $sucursalPivot->pivot->cantidad_fisica : 0;
            $cantidadMovimiento = $validados['tipo_ajuste'] === 'Sumar' ? $validados['cantidad'] : -$validados['cantidad'];
            $cantidadActual = $cantidadAnterior + $cantidadMovimiento;

            if ($cantidadActual < 0) {
                return redirect()->back()->with('error', 'El ajuste no puede dejar el stock físico en negativo.');
            }

            $producto->sucursales()->syncWithoutDetaching([
                $validados['sucursal_id'] => ['cantidad_fisica' => $cantidadActual]
            ]);

            DB::table('movimientos_stock')->insert([
                'producto_id' => $producto->id,
                'sucursal_id' => $validados['sucursal_id'],
                'user_id' => auth()->id(),
                'tipo_movimiento' => 'Ajuste Manual',
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_movimiento' => $cantidadMovimiento,
                'cantidad_actual' => $cantidadActual,
                'motivo' => $validados['motivo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('exito', 'Stock ajustado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al procesar el ajuste de stock: ' . $e->getMessage());
        }
    }

    public function auditoria(Request $request, Producto $producto)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && !$producto->sucursales()->where('comercio_id', $comercioId)->exists()) {
            abort(403, 'Este producto no pertenece a tu comercio.');
        }

        $query = DB::table('movimientos_stock')
            ->join('users', 'movimientos_stock.user_id', '=', 'users.id')
            ->join('sucursales', 'movimientos_stock.sucursal_id', '=', 'sucursales.id')
            ->where('producto_id', $producto->id)
            ->select('movimientos_stock.*', 'users.name as usuario', 'sucursales.nombre as sucursal')
            ->orderBy('movimientos_stock.created_at', 'desc');

        if ($request->filled('fecha_desde')) {
            $query->where('movimientos_stock.created_at', '>=', $request->fecha_desde . ' 00:00:00');
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('movimientos_stock.created_at', '<=', $request->fecha_hasta . ' 23:59:59');
        }

        $movimientos = $query->paginate(15);

        return response()->json($movimientos);
    }

    public function buscarPorCodigo(string $codigo, ProductLookupService $lookup, PromotionEngineService $engine)
    {
        $result = $lookup->lookup($codigo);

        if ($result->found) {
            $gp = $result->globalProduct;
            $comercioId = auth()->user()->branch?->comercio_id;

            $tenantProduct = Producto::where('codigo_barras', $codigo)
                ->where('estado', true)
                ->whereHas('sucursales', fn($q) => $q->where('comercio_id', $comercioId))
                ->first();

            $basePrice = $tenantProduct?->precio_venta
                ? (float) $tenantProduct->precio_venta
                : null;

            $promotions = $tenantProduct
                ? $engine->forProducto($tenantProduct, $comercioId, $basePrice)
                : new PromotionResult();

            return response()->json([
                'found' => true,
                'global_product' => [
                    'id' => $gp->id,
                    'nombre' => $gp->nombre,
                    'codigo_barras' => $gp->codigo_barras,
                    'marca' => $gp->marca,
                    'categoria' => $gp->categoria,
                    'presentacion' => $gp->presentacion,
                    'imagen' => $gp->imagen,
                    'descripcion' => $gp->descripcion,
                ],
                'promotions' => $promotions->toArray(),
                'source' => $result->source,
            ]);
        }

        return response()->json([
            'found' => false,
            'codigo_barras' => $codigo,
        ]);
    }

    public function exportar(Request $request)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $productos = Producto::with(['categoria', 'marca', 'proveedor', 'sucursales'])
            ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->whereIn('sucursales.id', $sucursalIds)))
            ->orderBy('id', 'desc')
            ->get();

        $sucursales = $sucursalIds->isNotEmpty()
            ? Sucursal::whereIn('id', $sucursalIds)->pluck('nombre', 'id')
            : collect();

        $user = auth()->user();

        $headers = [
            'nombre', 'codigo_barras', 'categoria', 'marca', 'proveedor',
            'precio_costo', 'precio_venta', 'stock_minimo', 'unidad_medida',
            'descripcion', 'es_retornable', 'estado', 'stock_total',
        ];

        foreach ($sucursales as $id => $nombre) {
            $headers[] = 'stock_' . str_replace(' ', '_', $nombre);
        }

        $callback = function () use ($productos, $headers, $sucursales, $user) {
            $file = fopen('php://output', 'w');

            fprintf($file, "# Exportado por: %s\n", $user->name);
            fprintf($file, "# Fecha: %s\n", now()->format('d/m/Y H:i'));
            fprintf($file, "# Comercio: %s\n", $user->branch?->comercio?->nombre ?? 'N/A');
            fprintf($file, "# Sucursales: %s\n", $sucursales->isEmpty() ? 'N/A' : $sucursales->implode(', '));
            fprintf($file, "# Total productos: %d\n", $productos->count());
            fputcsv($file, $headers);

            foreach ($productos as $p) {
                $row = [
                    $p->nombre,
                    $p->codigo_barras,
                    $p->categoria?->nombreCategoria,
                    $p->marca?->nombreMarca,
                    $p->proveedor?->razon_social,
                    $p->precio_costo,
                    $p->precio_venta,
                    $p->stock_minimo,
                    $p->unidad_medida,
                    $p->descripcion,
                    $p->es_retornable ? '1' : '0',
                    $p->estado ? '1' : '0',
                    $p->sucursales->sum(fn ($s) => (float) $s->pivot->cantidad_fisica),
                ];

                foreach ($sucursales as $id => $nombre) {
                    $suc = $p->sucursales->firstWhere('id', $id);
                    $row[] = $suc ? (float) $suc->pivot->cantidad_fisica : 0;
                }

                fputcsv($file, $row);
            }

            fclose($file);
        };

        $nombre = 'productos_' . now()->format('Ymd_His') . '.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $comercioId = auth()->user()->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $file = $request->file('archivo');
        $handle = fopen($file->getRealPath(), 'r');

        $headers = null;
        $creados = 0;
        $actualizados = 0;
        $errores = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || empty($row[0])) continue;

                $linea = trim($row[0]);
                if (str_starts_with($linea, '#')) continue;

                if ($headers === null) {
                    $headers = $row;
                    continue;
                }

                $data = array_combine($headers, $row);

                $categoriaId = $this->buscarOCrearReferencia(
                    'App\Models\Categoria', 'nombreCategoria', $data['categoria'] ?? null, ['estado' => true]
                );
                $marcaId = $this->buscarOCrearReferencia(
                    'App\Models\Marca', 'nombreMarca', $data['marca'] ?? null, ['estado' => true]
                );
                $proveedorId = $this->buscarOCrearReferencia(
                    'App\Models\Proveedor', 'razon_social', $data['proveedor'] ?? null, ['estado' => true]
                );

                $productoData = [
                    'nombre' => $data['nombre'] ?? null,
                    'codigo_barras' => $data['codigo_barras'] ?? null,
                    'categoria_id' => $categoriaId,
                    'marca_id' => $marcaId,
                    'proveedor_id' => $proveedorId,
                    'precio_costo' => $data['precio_costo'] ?? 0,
                    'precio_venta' => $data['precio_venta'] ?? 0,
                    'stock_minimo' => $data['stock_minimo'] ?? 0,
                    'unidad_medida' => in_array($data['unidad_medida'] ?? '', ['Unidad', 'Kg', 'Gramos']) ? $data['unidad_medida'] : 'Unidad',
                    'descripcion' => $data['descripcion'] ?? null,
                    'es_retornable' => ($data['es_retornable'] ?? '0') === '1',
                    'estado' => ($data['estado'] ?? '1') === '1',
                ];

                if (empty($productoData['nombre']) || empty($productoData['codigo_barras'])) {
                    $errores[] = 'Línea ' . count($errores) . ': nombre y código de barras son requeridos';
                    continue;
                }

                $existente = Producto::where('codigo_barras', $productoData['codigo_barras'])->first();

                if ($existente) {
                    $existente->update($productoData);
                    $actualizados++;
                } else {
                    $producto = Producto::create($productoData);
                    if ($sucursalIds->isNotEmpty()) {
                        $primeraSucursal = $sucursalIds->first();
                        $producto->sucursales()->attach($primeraSucursal, [
                            'cantidad_fisica' => 0,
                            'cantidad_reservada' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $creados++;
                }
            }

            DB::commit();
            fclose($handle);

            $mensaje = "Importación completada: {$creados} creados, {$actualizados} actualizados.";
            if (!empty($errores)) {
                $mensaje .= ' Errores: ' . implode(' | ', array_slice($errores, 0, 5));
            }

            return redirect()->back()->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            return redirect()->back()->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }

    public function pdf(Request $request)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $productos = Producto::with(['categoria', 'marca', 'proveedor', 'sucursales'])
            ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->whereIn('sucursales.id', $sucursalIds)))
            ->orderBy('nombre')
            ->get();

        $config = DB::table('configuraciones')
            ->whereIn('clave', ['nombre_empresa', 'logo_empresa', 'direccion_empresa', 'telefono_empresa', 'cuit'])
            ->pluck('valor', 'clave')
            ->toArray();

        $logoBase64 = null;
        if (!empty($config['logo_empresa'])) {
            $pathLogo = storage_path('app/public/' . $config['logo_empresa']);
            if (file_exists($pathLogo) && is_file($pathLogo)) {
                $ext = pathinfo($pathLogo, PATHINFO_EXTENSION);
                $logoBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($pathLogo));
            }
        }

        $sucursales = $sucursalIds->isNotEmpty()
            ? Sucursal::whereIn('id', $sucursalIds)->pluck('nombre', 'id')
            : collect();

        $user = auth()->user();

        $pdf = Pdf::loadView('pdf.productos', [
            'productos' => $productos,
            'config' => $config,
            'logo' => $logoBase64,
            'sucursales' => $sucursales,
            'usuario' => $user->name,
            'comercio' => $user->branch?->comercio?->nombre ?? ($config['nombre_empresa'] ?? 'Mi Negocio'),
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('productos_' . now()->format('Ymd_His') . '.pdf');
    }

    private function buscarOCrearReferencia(string $modelo, string $columna, ?string $valor, array $extra = []): ?int
    {
        if (empty($valor)) return null;

        $registro = $modelo::firstOrCreate(
            [$columna => $valor],
            $extra
        );

        return $registro->id;
    }

    public function generarPlu()
    {
        $maxPlu = DB::table('productos')
            ->whereRaw("codigo_barras ~ '^[0-9]{1,5}$'") 
            ->max(DB::raw('codigo_barras::integer'));

        $proximo = $maxPlu ? $maxPlu + 1 : 1000;

        $pluFormateado = str_pad($proximo, 4, '0', STR_PAD_LEFT);

        return response()->json(['plu_sugerido' => $pluFormateado]);
    }
}