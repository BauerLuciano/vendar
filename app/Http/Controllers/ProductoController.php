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
use Illuminate\Support\Str;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

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
                : collect(),
        ]);
    }

    public function store(Request $request, ProductLookupService $lookup)
    {
        $request->merge([
            'nombre' => trim((string) $request->nombre),
        ]);

        $validados = $request->validate([
            'nombre'              => 'required|string|min:4|max:255|regex:/\S/',
            'codigo_barras'       => 'required|string|min:2|max:14|regex:/^[0-9]+$/|unique:productos,codigo_barras',
            'categoria_id'        => 'required|exists:categorias,id',
            'marca_id'            => 'required|exists:marcas,id',
            'proveedor_id'        => 'required|exists:proveedores,id',
            'unidad_medida'       => 'required|in:Unidad,Kg',
            'unidad_compra'       => 'nullable|string|max:50',
            'cantidad_por_compra' => 'nullable|numeric|min:1',
            'es_retornable'       => 'boolean',
            'precio_costo'        => 'required|numeric|min:0',
            'precio_venta'        => 'required|numeric|min:0',
            'stock_minimo'        => 'required|numeric|min:0',
            'stock_objetivo'     => 'nullable|numeric|min:0',
            'stock_inicial'       => 'nullable|numeric|min:0',
            'descripcion'         => 'nullable|string',
            'imagen'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'imagen_url'          => 'nullable|url',
        ], [
            'nombre.min' => 'El nombre debe tener al menos 4 caracteres.',
            'nombre.regex' => 'El nombre no puede estar compuesto solo por espacios.',
            'codigo_barras.regex' => 'El código de barras solo puede contener números.',
            'codigo_barras.min' => 'El código debe tener al menos 2 números.',
            'codigo_barras.max' => 'El código no puede superar los 14 números.',
            'cantidad_por_compra.min' => 'La cantidad por compra debe ser al menos 1.',
        ]);

        if ($validados['precio_costo'] >= $validados['precio_venta']) {
            return back()->withErrors([
                'precio_venta' => 'El precio de venta debe ser mayor al precio de costo.',
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('imagen')) {
                $validados['imagen'] = $request->file('imagen')->store('productos', 'public');
            } elseif (!empty($validados['imagen_url'])) {
                try {
                    $imageUrl = filter_var($validados['imagen_url'], FILTER_VALIDATE_URL);
                    $host = $imageUrl ? parse_url($imageUrl, PHP_URL_HOST) : null;
                    $ip = $host ? gethostbyname($host) : null;

                    $blocked = !$imageUrl
                        || parse_url($imageUrl, PHP_URL_SCHEME) !== 'https'
                        || !$ip
                        || in_array($ip, ['127.0.0.1', '::1'])
                        || str_starts_with($ip, '10.')
                        || str_starts_with($ip, '172.')
                        || str_starts_with($ip, '192.168.')
                        || str_starts_with($ip, '169.254.')
                        || $host === 'localhost';

                    if ($blocked) {
                        Log::warning("Blocked imagen_url download (SSRF): {$validados['imagen_url']}");
                    } else {
                        $imageContents = Http::timeout(10)->get($validados['imagen_url'])->body();
                        if ($imageContents) {
                            $filename = 'productos/' . Str::uuid() . '.jpg';
                            Storage::disk('public')->put($filename, $imageContents);
                            $validados['imagen'] = $filename;
                        }
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

        $request->merge([
            'nombre' => trim((string) $request->nombre),
        ]);

        $validados = $request->validate([
            'nombre'              => 'required|string|min:4|max:255|regex:/\S/',
            'codigo_barras'       => ['required', 'string', 'min:2', 'max:14', 'regex:/^[0-9]+$/', Rule::unique('productos')->ignore($producto->id)],
            'categoria_id'        => 'required|exists:categorias,id',
            'marca_id'            => 'required|exists:marcas,id',
            'proveedor_id'        => 'required|exists:proveedores,id',
            'unidad_medida'       => 'required|in:Unidad,Kg',
            'unidad_compra'       => 'nullable|string|max:50',
            'cantidad_por_compra' => 'nullable|numeric|min:1',
            'es_retornable'       => 'boolean',
            'precio_costo'        => 'required|numeric|min:0',
            'precio_venta'        => 'required|numeric|min:0',
            'stock_minimo'        => 'required|numeric|min:0',
            'stock_objetivo'     => 'nullable|numeric|min:0',
            'descripcion'         => 'nullable|string',
            'imagen'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'imagen_url'          => 'nullable|url',
        ], [
            'nombre.min' => 'El nombre debe tener al menos 4 caracteres.',
            'nombre.regex' => 'El nombre no puede estar compuesto solo por espacios.',
            'codigo_barras.regex' => 'El código de barras solo puede contener números.',
            'cantidad_por_compra.min' => 'La cantidad por compra debe ser al menos 1.',
        ]);

        if ($validados['precio_costo'] >= $validados['precio_venta']) {
            return back()->withErrors([
                'precio_venta' => 'El precio de venta debe ser mayor al precio de costo.',
            ])->withInput();
        }

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
                $validados['sucursal_id'] => [
                    'cantidad_fisica'    => $cantidadActual,
                    'cantidad_reservada' => $sucursalPivot ? $sucursalPivot->pivot->cantidad_reservada : 0,
                ]
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
        if ($request->filled('sucursal_id')) {
            $query->where('movimientos_stock.sucursal_id', $request->sucursal_id);
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

    public function buscarSimilares(Request $request)
    {
        $request->validate(['q' => 'required|string|min:4']);

        $comercioId = auth()->user()->branch?->comercio_id;
        $termino = trim($request->q);

        $productos = Producto::with('marca')
            ->where('nombre', 'ILIKE', '%' . $termino . '%')
            ->where('estado', true)
            ->when($comercioId, fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->where('comercio_id', $comercioId)))
            ->select('id', 'nombre', 'codigo_barras', 'unidad_medida', 'estado', 'marca_id')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'codigo_barras' => $p->codigo_barras,
                'unidad_medida' => $p->unidad_medida,
                'estado' => $p->estado,
                'marca_nombre' => $p->marca?->nombreMarca,
            ])
            ->sortBy(function ($p) use ($termino) {
                $nombreLower = mb_strtolower($p['nombre']);
                $terminoLower = mb_strtolower($termino);
                if ($nombreLower === $terminoLower) return 0;
                if (str_starts_with($nombreLower, $terminoLower)) return 1;
                return 2;
            })
            ->values();

        return response()->json($productos);
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

        $user = auth()->user();
        $comercioNombre = $user->branch?->comercio?->nombre ?? 'Productos';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', $comercioNombre);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:N2');
        $sheet->setCellValue('A2', 'Exportado por: ' . $user->name . ' | Fecha: ' . now()->format('d/m/Y H:i') . ' | Total: ' . $productos->count() . ' productos');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('666666'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'Nombre', 'Código de Barras', 'Categoría', 'Marca', 'Proveedor',
            'Precio Costo', 'Precio Venta', 'Stock Mínimo', 'Unidad',
            'Unidad Compra', 'Cant. por Compra', 'Descripción', 'Retornable', 'Estado',
        ];

        $headerRow = 4;
        foreach ($headers as $col => $header) {
            $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $headerRow;
            $sheet->setCellValue($ref, $header);
            $sheet->getStyle($ref)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $fill = $sheet->getStyle($ref)->getFill();
            $fill->setFillType(Fill::FILL_SOLID)->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));
            $sheet->getStyle($ref)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($ref)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        }

        $dataRow = $headerRow + 1;
        foreach ($productos as $p) {
            $row = [
                $p->nombre,
                $p->codigo_barras,
                $p->categoria?->nombreCategoria ?? '',
                $p->marca?->nombreMarca ?? '',
                $p->proveedor?->razon_social ?? '',
                $p->precio_costo ? (float) $p->precio_costo : '',
                $p->precio_venta ? (float) $p->precio_venta : '',
                $p->stock_minimo ? (int) $p->stock_minimo : '',
                $p->unidad_medida ?? '',
                $p->unidad_compra ?? '',
                $p->cantidad_por_compra ?? '',
                $p->descripcion ?? '',
                $p->es_retornable ? 'Sí' : 'No',
                $p->estado ? 'Activo' : 'Inactivo',
            ];

            foreach ($row as $col => $value) {
                $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $dataRow;
                $sheet->setCellValue($ref, $value);
                $borders = $sheet->getStyle($ref)->getBorders();
                $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D1D5DB'));
                $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D1D5DB'));
                $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D1D5DB'));
                $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D1D5DB'));

                if ($col === 5 || $col === 6) {
                    $sheet->getStyle($ref)->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }

            if (($dataRow - $headerRow) % 2 === 0) {
                foreach (range(1, count($headers)) as $col) {
                    $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $dataRow;
                    $sheet->getStyle($ref)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F3F4F6'));
                }
            }

            $dataRow++;
        }

        foreach (range(1, count($headers)) as $col) {
            $maxLen = strlen($headers[$col - 1]);
            for ($r = $headerRow + 1; $r < $dataRow; $r++) {
                $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $r;
                $val = $sheet->getCell($ref)->getValue();
                $maxLen = max($maxLen, strlen((string) $val));
            }
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col))->setWidth(min($maxLen + 4, 40));
        }

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $nombre = 'productos_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/' . $nombre);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tempPath, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ], [
            'archivo.required' => 'Debés seleccionar un archivo para importar.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.mimes' => 'El formato no es compatible. Usá CSV o Excel (.xlsx, .xls).',
            'archivo.max' => 'El archivo supera el tamaño máximo de 5 MB.',
        ]);

        $comercioId = auth()->user()->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $file = $request->file('archivo');
        $ext = strtolower($file->getClientOriginalExtension());

        $rows = [];
        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = [];
                foreach ($row->getCellIterator() as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $rows[] = $rowData;
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }

        $headers = null;
        $creados = 0;
        $actualizados = 0;
        $errores = [];

        $headerMap = [
            'nombre' => 'nombre', 'código de barras' => 'codigo_barras', 'codigo_barras' => 'codigo_barras',
            'categoría' => 'categoria', 'categoria' => 'categoria',
            'marca' => 'marca', 'proveedor' => 'proveedor',
            'precio costo' => 'precio_costo', 'precio_costo' => 'precio_costo',
            'precio venta' => 'precio_venta', 'precio_venta' => 'precio_venta',
            'stock mínimo' => 'stock_minimo', 'stock_minimo' => 'stock_minimo', 'stock minimo' => 'stock_minimo',
            'unidad' => 'unidad_medida', 'unidad_medida' => 'unidad_medida',
            'unidad compra' => 'unidad_compra', 'unidad_compra' => 'unidad_compra',
            'cant. por compra' => 'cantidad_por_compra', 'cantidad_por_compra' => 'cantidad_por_compra', 'cantidad por compra' => 'cantidad_por_compra',
            'descripción' => 'descripcion', 'descripcion' => 'descripcion',
            'retornable' => 'es_retornable', 'es_retornable' => 'es_retornable',
            'estado' => 'estado',
        ];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                if (empty($row) || empty($row[0])) continue;

                $linea = trim((string) $row[0]);
                if (str_starts_with($linea, '#')) continue;

                if ($headers === null) {
                    $rawHeaders = array_map(fn ($h) => trim(mb_strtolower((string) $h)), $row);
                    $mappedHeaders = array_map(fn ($h) => $headerMap[$h] ?? $h, $rawHeaders);

                    if (!in_array('nombre', $mappedHeaders) || !in_array('codigo_barras', $mappedHeaders)) {
                        continue;
                    }

                    $headers = $mappedHeaders;
                    continue;
                }

                $trimmedRow = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

                if (count($trimmedRow) < count($headers)) {
                    $trimmedRow = array_pad($trimmedRow, count($headers), '');
                } elseif (count($trimmedRow) > count($headers)) {
                    $trimmedRow = array_slice($trimmedRow, 0, count($headers));
                }

                $allData = array_combine($headers, $trimmedRow);

                $data = $allData;

                $categoriaId = $this->buscarOCrearReferencia(
                    'App\Models\Categoria', 'nombreCategoria', $data['categoria'] ?? null, ['estado' => true], $comercioId
                );
                $marcaId = $this->buscarOCrearReferencia(
                    'App\Models\Marca', 'nombreMarca', $data['marca'] ?? null, ['estado' => true], $comercioId
                );
                $proveedorId = $this->buscarOCrearReferencia(
                    'App\Models\Proveedor', 'razon_social', $data['proveedor'] ?? null, ['estado' => true], $comercioId
                );

                $productoData = [
                    'nombre' => $data['nombre'] ?? null,
                    'codigo_barras' => $data['codigo_barras'] ?? null,
                    'categoria_id' => $categoriaId,
                    'marca_id' => $marcaId,
                    'proveedor_id' => $proveedorId,
                    'precio_costo' => is_numeric($data['precio_costo'] ?? null) ? $data['precio_costo'] : 0,
                    'precio_venta' => is_numeric($data['precio_venta'] ?? null) ? $data['precio_venta'] : 0,
                    'stock_minimo' => is_numeric($data['stock_minimo'] ?? null) ? $data['stock_minimo'] : 0,
                    'unidad_medida' => in_array(strtolower($data['unidad_medida'] ?? ''), ['unidad', 'kg', 'gramos']) ? ucfirst($data['unidad_medida']) : 'Unidad',
                    'unidad_compra' => !empty($data['unidad_compra']) ? $data['unidad_compra'] : null,
                    'cantidad_por_compra' => !empty($data['cantidad_por_compra']) && is_numeric($data['cantidad_por_compra']) ? $data['cantidad_por_compra'] : null,
                    'descripcion' => $data['descripcion'] ?? null,
                    'es_retornable' => in_array(strtolower(trim($data['es_retornable'] ?? '0')), ['1', 'sí', 'si']),
                    'estado' => in_array(strtolower(trim($data['estado'] ?? '1')), ['1', 'activo']),
                ];

                if (empty($productoData['nombre']) || empty($productoData['codigo_barras'])) {
                    $errores[] = 'Línea ' . count($errores) . ': nombre y código de barras son requeridos';
                    continue;
                }

                $pc = (float) ($productoData['precio_costo'] ?? 0);
                $pv = (float) ($productoData['precio_venta'] ?? 0);
                if ($pc > 0 && $pv <= $pc) {
                    $errores[] = 'Línea ' . (count($errores) + 1) . ': precio de venta ($' . number_format($pv, 2) . ') debe ser mayor al costo ($' . number_format($pc, 2) . ') en "' . ($productoData['nombre'] ?? 'sin nombre') . '"';
                    continue;
                }

                $existente = Producto::where('codigo_barras', $productoData['codigo_barras'])
                    ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->whereIn('sucursales.id', $sucursalIds)))
                    ->first();

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

            $mensaje = "Importación completada: {$creados} creados, {$actualizados} actualizados.";
            if (!empty($errores)) {
                $mensaje .= ' Errores: ' . implode(' | ', array_slice($errores, 0, 5));
            }

            return response()->json(['success' => true, 'message' => $mensaje]);

        } catch (\Exception $e) {
            DB::rollBack();
            $msg = $e->getMessage();
            if (str_contains($msg, 'array_combine')) {
                $msg = 'El archivo tiene un formato inválido. Verificá que todas las filas tengan la misma cantidad de columnas que el encabezado.';
            }
            return response()->json(['error' => 'Error al importar: ' . $msg], 500);
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

        $user = auth()->user();

        $logoBase64 = null;
        if (!empty($config['logo_empresa'])) {
            $pathLogo = storage_path('app/public/' . $config['logo_empresa']);
            if (file_exists($pathLogo) && is_file($pathLogo)) {
                $ext = pathinfo($pathLogo, PATHINFO_EXTENSION);
                $logoBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($pathLogo));
            }
        }
        if (!$logoBase64 && $user->branch?->comercio?->logo) {
            $pathLogo = storage_path('app/public/' . $user->branch->comercio->logo);
            if (file_exists($pathLogo) && is_file($pathLogo)) {
                $ext = pathinfo($pathLogo, PATHINFO_EXTENSION);
                $logoBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($pathLogo));
            }
        }

        $sucursales = $sucursalIds->isNotEmpty()
            ? Sucursal::whereIn('id', $sucursalIds)->pluck('nombre', 'id')
            : collect();

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

    private function buscarOCrearReferencia(string $modelo, string $columna, ?string $valor, array $extra = [], ?int $comercioId = null): ?int
    {
        if (empty($valor)) return null;

        $registro = $modelo::where($columna, $valor)
            ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId)->orWhereNull('comercio_id'))
            ->first();

        if (!$registro) {
            $extra[$columna] = $valor;
            if ($comercioId) {
                $extra['comercio_id'] = $comercioId;
            }
            $registro = $modelo::create($extra);
        }

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