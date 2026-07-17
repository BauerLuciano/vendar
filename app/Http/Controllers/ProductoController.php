<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Services\ProductLookupService;
use App\Services\SucursalScopeService;
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
    public function __construct(private SucursalScopeService $scope) {}

    public function index()
    {
        $comercioId = $this->scope->obtenerComercioId();
        $sucursalIds = $this->scope->obtenerSucursalesPermitidasIds();

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
            'categoria_id'        => 'nullable|exists:categorias,id',
            'marca_id'            => 'nullable|exists:marcas,id',
            'proveedor_id'        => 'nullable|exists:proveedores,id',
            'unidad_medida'       => 'required|in:Unidad,Kg,Gramos',
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

            $sucursalId = $this->scope->obtenerSucursalActiva()?->id;
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
        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId && !$producto->sucursales()->where('comercio_id', $comercioId)->exists()) {
            abort(403, 'Este producto no pertenece a tu comercio.');
        }

        $request->merge([
            'nombre' => trim((string) $request->nombre),
        ]);

        $validados = $request->validate([
            'nombre'              => 'required|string|min:4|max:255|regex:/\S/',
            'codigo_barras'       => ['required', 'string', 'min:2', 'max:14', 'regex:/^[0-9]+$/', Rule::unique('productos')->ignore($producto->id)],
            'categoria_id'        => 'nullable|exists:categorias,id',
            'marca_id'            => 'nullable|exists:marcas,id',
            'proveedor_id'        => 'nullable|exists:proveedores,id',
            'unidad_medida'       => 'required|in:Unidad,Kg,Gramos',
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
        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId && !$producto->sucursales()->where('comercio_id', $comercioId)->exists()) {
            abort(403, 'Este producto no pertenece a tu comercio.');
        }

        $producto->update(['estado' => !$producto->estado]);
        return redirect()->back()->with('success', 'Estado modificado.');
    }

    public function ajustarStock(Request $request, Producto $producto)
    {
        $comercioId = $this->scope->obtenerComercioId();
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
        $comercioId = $this->scope->obtenerComercioId();
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
            $comercioId = $this->scope->obtenerComercioId();

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

        $comercioId = $this->scope->obtenerComercioId();
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
        $comercioId = $this->scope->obtenerComercioId();
        $sucursalIds = $this->scope->obtenerSucursalesPermitidasIds();

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
                $p->stock_minimo !== null && $p->stock_minimo !== '' ? (float) $p->stock_minimo : '',
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

    public function plantilla()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'Plantilla de Productos');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:N2');
        $sheet->setCellValue('A2', 'Completá este archivo y importalo desde el sistema. No modifiques los nombres de las columnas.');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('666666'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:N3');
        $sheet->setCellValue('A3', '# Las filas de abajo son EJEMPLO — borralas y poné tus productos');
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('999999'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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

        $ejemplos = [
            ['Coca Cola 500ml', '7790000000012', 'Bebidas', 'Coca Cola', '', 350.00, 550.00, 10, 'Unidad', 'Unidad', 12, 'Gaseosa 500ml', 'Sí', 'Activo'],
            ['Papa Lays', '7790000000029', 'Snacks', 'Lays', '', 200.00, 380.00, 5, 'Unidad', 'Unidad', 6, 'Papa frita porción', 'No', 'Activo'],
        ];

        $dataRow = $headerRow + 1;
        foreach ($ejemplos as $row) {
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

        $nombre = 'plantilla_productos.xlsx';
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

        $comercioId = $this->scope->obtenerComercioId();
        $sucursalIds = $this->scope->obtenerSucursalesPermitidasIds();

        $file = $request->file('archivo');
        $ext = strtolower($file->getClientOriginalExtension());

        // === FASE 1: LEER ARCHIVO ===
        $rows = $this->leerArchivo($file, $ext);
        if (empty($rows)) {
            return response()->json(['success' => false, 'error' => 'El archivo está vacío.'], 422);
        }

        // === FASE 2: DETECTAR HEADERS ===
        $headerMap = $this->obtenerMapeoHeadersImportacion();
        $headerResult = $this->detectarHeaders($rows, $headerMap);

        if (!$headerResult['found']) {
            return response()->json(['success' => false, 'error' => $headerResult['error']], 422);
        }

        $mappedHeaders = $headerResult['mapped'];
        $headerIndex = $headerResult['index'];

        if (!in_array('codigo_barras', $mappedHeaders) && !$this->tieneColumnaIdentificadorAlternativo($mappedHeaders)) {
            $debugMapped = implode(', ', $mappedHeaders);
            return response()->json(['success' => false, 'error' => 'Falta columna de identificador. Columnas: [' . $debugMapped . ']. Se necesita "Código de Barras" o "PLU".'], 422);
        }

        // Recortar filas: quitar metadata antes de headers y los headers mismos
        $dataRows = array_slice($rows, $headerIndex + 1);

        // === FASE 3: NORMALIZAR TODAS LAS FILAS ===
        $filasNormalizadas = [];
        $numFilaBase = 1;

        foreach ($dataRows as $rowIndex => $row) {
            $numFila = $numFilaBase + $rowIndex;

            $trimmedRow = array_map(fn($v) => $this->celdaAString($v), $row);
            if (count($trimmedRow) < count($mappedHeaders)) {
                $trimmedRow = array_pad($trimmedRow, count($mappedHeaders), '');
            } elseif (count($trimmedRow) > count($mappedHeaders)) {
                $trimmedRow = array_slice($trimmedRow, 0, count($mappedHeaders));
            }

            $data = array_combine($mappedHeaders, $trimmedRow);

            // Filas vacías o de comentario
            $allEmpty = true;
            foreach ($data as $v) {
                if (trim((string) $v) !== '') { $allEmpty = false; break; }
            }
            if ($allEmpty) continue;
            if (str_starts_with(trim((string) ($data['nombre'] ?? '')), '#')) continue;

            // Identificador: codigo_barras o plu (todo va a parar en codigo_barras)
            $codigoBarrasRaw = trim((string) ($data['codigo_barras'] ?? ''));
            $pluRaw = trim((string) ($data['plu'] ?? ''));
            $skuRaw = trim((string) ($data['sku'] ?? ''));

            $identificador = $codigoBarrasRaw ?: ($pluRaw ?: $skuRaw);

            $filasNormalizadas[] = [
                'num_fila' => $numFila,
                'data' => $data,
                'nombre_raw' => trim((string) ($data['nombre'] ?? '')),
                'identificador' => $identificador,
                'identificador_fuente' => $codigoBarrasRaw ? 'codigo_barras' : ($pluRaw ? 'plu' : 'sku'),
            ];
        }

        // === FASE 4: VALIDAR TODAS LAS FILAS ===
        $errores = [];
        $warnings = [];
        $conflictos = [];
        $barrasEnArchivo = [];
        $filasValidas = [];

        // Precargar códigos existentes en DB (performance: una sola query)
        $identificadoresEnArchivo = array_unique(array_column($filasNormalizadas, 'identificador'));
        $identificadoresEnArchivo = array_filter($identificadoresEnArchivo);
        $existentesEnDB = [];
        if (!empty($identificadoresEnArchivo)) {
            $existentesEnDB = Producto::whereIn('codigo_barras', $identificadoresEnArchivo)
                ->get()
                ->keyBy('codigo_barras')
                ->toArray();
        }

        foreach ($filasNormalizadas as $fila) {
            $numFila = $fila['num_fila'];
            $data = $fila['data'];
            $nombreRaw = $fila['nombre_raw'];
            $identificador = $fila['identificador'];
            $filaErrores = [];

            // Validar nombre
            $nombre = $this->normalizarString($nombreRaw);
            if (empty($nombre)) {
                $filaErrores[] = 'El nombre del producto es obligatorio.';
            }

            // Validar identificador
            if (empty($identificador)) {
                $filaErrores[] = 'El producto debe tener un código de barras o un PLU.';
            }

            // Validar duplicado dentro del archivo
            if (!empty($identificador)) {
                if (isset($barrasEnArchivo[$identificador])) {
                    $filaErrores[] = "El código \"{$identificador}\" ya fue utilizado en la fila {$barrasEnArchivo[$identificador]} del mismo archivo.";
                } else {
                    $barrasEnArchivo[$identificador] = $numFila;
                }
            }

            // Detectar conflicto: mismo código, nombre diferente en DB
            $esNuevo = true;
            $existente = null;
            if (!empty($identificador) && isset($existentesEnDB[$identificador])) {
                $existente = $existentesEnDB[$identificador];
                $esNuevo = false;
                $nombreExistente = $this->normalizarString($existente['nombre']);
                if ($nombre !== $nombreExistente) {
                    $conflictos[] = [
                        'fila' => $numFila,
                        'codigo' => $identificador,
                        'producto_existente' => $existente['nombre'],
                        'producto_importado' => $nombreRaw,
                        'mensaje' => "El código \"{$identificador}\" ya pertenece al producto \"{$existente['nombre']}\", pero el archivo intenta importarlo como \"{$nombreRaw}\".",
                    ];
                    continue;
                }
            }

            // Validar precio
            $precioCostoRaw = $this->normalizarPrecioArgentino($data['precio_costo'] ?? '');
            $precioVentaRaw = $this->normalizarPrecioArgentino($data['precio_venta'] ?? '');

            if ($precioCostoRaw !== null && $precioCostoRaw < 0) {
                $filaErrores[] = 'El precio de costo no puede ser negativo.';
            }
            if ($precioVentaRaw !== null && $precioVentaRaw < 0) {
                $filaErrores[] = 'El precio de venta no puede ser negativo.';
            }

            // Para merge: si es existente y un precio viene vacío, usar el de DB para validar
            $pc = $precioCostoRaw !== null ? $precioCostoRaw : ($existente ? (float) $existente['precio_costo'] : 0);
            $pv = $precioVentaRaw !== null ? $precioVentaRaw : ($existente ? (float) $existente['precio_venta'] : 0);
            if ($pc > 0 && $pv > 0 && $pv <= $pc) {
                $filaErrores[] = "Precio de venta (\${$pv}) debe ser mayor al precio de costo (\${$pc}).";
            }

            // Validar unidad de medida
            $unidadMedida = $this->normalizarUnidadMedida($data['unidad_medida'] ?? '');

            // Validar stock mínimo
            $stockMinimoRaw = $data['stock_minimo'] ?? '';
            if ($stockMinimoRaw !== '' && $stockMinimoRaw !== null) {
                $stockMinimoParsed = $this->normalizarPrecioArgentino($stockMinimoRaw);
                if ($stockMinimoParsed !== null && $stockMinimoParsed < 0) {
                    $filaErrores[] = 'El stock mínimo no puede ser negativo.';
                }
            }

            // Si hay errores de esta fila, acumular y saltar
            if (!empty($filaErrores)) {
                foreach ($filaErrores as $msg) {
                    $errores[] = ['fila' => $numFila, 'tipo' => 'validacion', 'mensaje' => $msg];
                }
                continue;
            }

            $filasValidas[] = [
                'num_fila' => $numFila,
                'data' => $data,
                'nombre' => $nombre,
                'nombre_raw' => $nombreRaw,
                'identificador' => $identificador,
                'es_nuevo' => $esNuevo,
                'existente' => $existente,
            ];
        }

        // Si no hay filas válidas, retornar error
        if (empty($filasValidas) && empty($conflictos)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron filas válidas para importar.',
                'resumen' => [
                    'total_filas' => count($filasNormalizadas),
                    'creados' => 0,
                    'actualizados' => 0,
                    'omitidos' => count($errores),
                    'conflictos' => count($conflictos),
                    'warnings' => count($warnings),
                ],
                'errores' => $errores,
                'conflictos' => $conflictos,
                'warnings' => $warnings,
            ], 422);
        }

        // === FASE 5: IMPORTAR FILAS VÁLIDAS ===
        $creados = 0;
        $actualizados = 0;
        $omitidos = count($errores) + count($conflictos);

        // 5a: Resolver referencias FUERA de la transacción
        foreach ($filasValidas as &$fila) {
            $data = $fila['data'];
            $numFila = $fila['num_fila'];

            $refCategoria = $this->resolverReferencia($data['categoria'] ?? '', Categoria::class, 'nombreCategoria', $comercioId);
            $fila['categoria_id'] = $refCategoria['id'];
            if ($refCategoria['warning']) $warnings[] = ['fila' => $numFila, 'mensaje' => $refCategoria['warning']];

            $refMarca = $this->resolverReferencia($data['marca'] ?? '', Marca::class, 'nombreMarca', $comercioId);
            $fila['marca_id'] = $refMarca['id'];
            if ($refMarca['warning']) $warnings[] = ['fila' => $numFila, 'mensaje' => $refMarca['warning']];

            $refProveedor = $this->resolverReferencia($data['proveedor'] ?? '', Proveedor::class, 'razon_social', $comercioId);
            $fila['proveedor_id'] = $refProveedor['id'];
            if ($refProveedor['warning']) $warnings[] = ['fila' => $numFila, 'mensaje' => $refProveedor['warning']];
        }
        unset($fila);

        // 5b: Crear/actualizar productos DENTRO de la transacción
        DB::beginTransaction();
        try {
            foreach ($filasValidas as $fila) {
                $numFila = $fila['num_fila'];
                $data = $fila['data'];
                $nombre = $fila['nombre'];
                $nombreRaw = $fila['nombre_raw'];
                $identificador = $fila['identificador'];
                $esNuevo = $fila['es_nuevo'];
                $existente = $fila['existente'];
                $categoriaId = $fila['categoria_id'];
                $marcaId = $fila['marca_id'];
                $proveedorId = $fila['proveedor_id'];

                // Precios
                $precioCosto = $this->normalizarPrecioArgentino($data['precio_costo'] ?? '');
                $precioVenta = $this->normalizarPrecioArgentino($data['precio_venta'] ?? '');

                // Unidad
                $unidadMedida = $this->normalizarUnidadMedida($data['unidad_medida'] ?? '');

                // Stock mínimo
                $stockMinimoRaw = $data['stock_minimo'] ?? '';
                $stockMinimo = $stockMinimoRaw !== '' && $stockMinimoRaw !== null
                    ? (int) round((float) $this->normalizarPrecioArgentino($stockMinimoRaw))
                    : null;

                // Cantidad por compra
                $cantidadCompraRaw = $data['cantidad_por_compra'] ?? '';
                $cantidadCompra = $cantidadCompraRaw !== '' && $cantidadCompraRaw !== null
                    ? round((float) $this->normalizarPrecioArgentino($cantidadCompraRaw), 2)
                    : null;

                // Booleanos
                $esRetornable = $this->parsearBooleano($data['es_retornable'] ?? '');
                $estado = $this->parsearBooleano($data['estado'] ?? '', true);

                // Descripción y unidad compra
                $descripcion = !empty(trim((string) ($data['descripcion'] ?? ''))) ? trim((string) $data['descripcion']) : null;
                $unidadCompra = !empty(trim((string) ($data['unidad_compra'] ?? ''))) ? trim((string) $data['unidad_compra']) : null;

                if ($esNuevo) {
                    // CREAR NUEVO
                    if (!$comercioId) {
                        $errores[] = ['fila' => $numFila, 'tipo' => 'error', 'mensaje' => 'No se pudo determinar el comercio.'];
                        $omitidos++;
                        continue;
                    }
                    $primeraSucursal = $this->scope->obtenerSucursalesDelComercio()->first();
                    if (!$primeraSucursal) {
                        $errores[] = ['fila' => $numFila, 'tipo' => 'error', 'mensaje' => 'No hay sucursales para asociar el producto.'];
                        $omitidos++;
                        continue;
                    }

                    $productoData = [
                        'nombre' => $nombre,
                        'codigo_barras' => $identificador,
                        'categoria_id' => $categoriaId,
                        'marca_id' => $marcaId,
                        'proveedor_id' => $proveedorId,
                        'precio_costo' => $precioCosto ?? 0,
                        'precio_venta' => $precioVenta ?? 0,
                        'stock_minimo' => $stockMinimo ?? 0,
                        'unidad_medida' => $unidadMedida,
                        'unidad_compra' => $unidadCompra,
                        'cantidad_por_compra' => $cantidadCompra,
                        'descripcion' => $descripcion,
                        'es_retornable' => $esRetornable,
                        'estado' => $estado,
                    ];

                    try {
                        $nuevoProducto = Producto::create($productoData);
                        $nuevoProducto->sucursales()->attach($primeraSucursal->id, [
                            'cantidad_fisica' => 0,
                            'cantidad_reservada' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $creados++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if (str_contains($e->getMessage(), 'duplicate key') || str_contains($e->getCode(), '23505')) {
                            $errores[] = ['fila' => $numFila, 'tipo' => 'error', 'mensaje' => "El código \"{$identificador}\" ya existe en la base de datos (creado por otra importación simultánea)."];
                            $omitidos++;
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    // ACTUALIZAR EXISTENTE — merge parcial
                    $mergeData = [];
                    if ($categoriaId !== null) $mergeData['categoria_id'] = $categoriaId;
                    if ($marcaId !== null) $mergeData['marca_id'] = $marcaId;
                    if ($proveedorId !== null) $mergeData['proveedor_id'] = $proveedorId;
                    if ($precioCosto !== null) $mergeData['precio_costo'] = round($precioCosto, 2);
                    if ($precioVenta !== null) $mergeData['precio_venta'] = round($precioVenta, 2);
                    if ($stockMinimo !== null) $mergeData['stock_minimo'] = $stockMinimo;
                    if ($unidadMedida !== null) $mergeData['unidad_medida'] = $unidadMedida;
                    if ($cantidadCompra !== null) $mergeData['cantidad_por_compra'] = $cantidadCompra;
                    if ($unidadCompra !== null) $mergeData['unidad_compra'] = $unidadCompra;
                    if ($descripcion !== null) $mergeData['descripcion'] = $descripcion;

                    // Siempre actualizar nombre, retornable y estado si vienen del Excel
                    $mergeData['nombre'] = $nombre;
                    $mergeData['es_retornable'] = $esRetornable;
                    $mergeData['estado'] = $estado;

                    if (!empty($mergeData)) {
                        Producto::where('id', $existente['id'])->update($mergeData);
                    }
                    $actualizados++;
                }
            }

            DB::commit();

            $totalFilas = count($filasNormalizadas);

            return response()->json([
                'success' => true,
                'message' => "Importación finalizada: {$creados} creados, {$actualizados} actualizados, {$omitidos} omitidos, " . count($conflictos) . " conflictos.",
                'resumen' => [
                    'total_filas' => $totalFilas,
                    'creados' => $creados,
                    'actualizados' => $actualizados,
                    'omitidos' => $omitidos,
                    'conflictos' => count($conflictos),
                    'warnings' => count($warnings),
                ],
                'errores' => $errores,
                'conflictos' => $conflictos,
                'warnings' => $warnings,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $msg = $e->getMessage();
            if (str_contains($msg, 'array_combine')) {
                $msg = 'El archivo tiene un formato inválido. Verificá columnas.';
            }
            return response()->json(['success' => false, 'error' => 'Error al importar: ' . $msg], 500);
        }
    }

    private function leerArchivo($file, string $ext): array
    {
        $rows = [];
        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $rawValue = $cell->getValue();
                    if ($rawValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        $rowData[] = $rawValue->getPlainText();
                    } else {
                        $rowData[] = $cell->getValue();
                    }
                }
                $rows[] = $rowData;
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }
        return $rows;
    }

    private function detectarHeaders(array $rows, array $headerMap): array
    {
        $maxSearch = min(10, count($rows));

        for ($i = 0; $i < $maxSearch; $i++) {
            $candidateRaw = array_map(fn($h) => $this->celdaAString($h), $rows[$i]);
            $candidate = array_map(function ($h) {
                $h = str_replace("\0", '', $h);
                $h = str_replace("\xc2\xa0", ' ', $h);
                $h = str_replace("\xa0", ' ', $h);
                $h = trim(mb_strtolower($h));
                $h = preg_replace('/\s+/', ' ', $h);
                return $h;
            }, $candidateRaw);
            $candidate = array_map(fn($h) => strtr($h, $this->obtenerMapaAcentos()), $candidate);

            $candidateMapped = array_map(fn($h) => $headerMap[$h] ?? $h, $candidate);

            if (in_array('nombre', $candidateMapped)) {
                Log::info('Importación: headers detectados en fila ' . ($i + 1), [
                    'mapped' => array_slice($candidateMapped, 0, 15),
                ]);
                return ['found' => true, 'mapped' => $candidateMapped, 'index' => $i];
            }
        }

        $lastRaw = !empty($candidateRaw) ? implode(', ', array_slice($candidateRaw, 0, 10)) : '(ninguna)';
        return ['found' => false, 'error' => 'No se encontraron columnas válidas. Última fila analizada: ' . $lastRaw];
    }

    private function resolverReferencia(string $valor, string $modelo, string $columna, ?int $comercioId): array
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return ['id' => null, 'warning' => null];
        }

        $id = $this->buscarOCrearReferencia($modelo, $columna, $valor, ['estado' => true], $comercioId);

        if ($id === null) {
            $nombreModelo = class_basename($modelo);
            return ['id' => null, 'warning' => "No se pudo crear el registro \"{$valor}\" en {$nombreModelo}. Se guardó sin esta referencia."];
        }

        return ['id' => $id, 'warning' => null];
    }

    public function pdf(Request $request)
    {
        $comercioId = $this->scope->obtenerComercioId();
        $sucursalIds = $this->scope->obtenerSucursalesPermitidasIds();

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

        $valorNormalizado = $this->normalizarString($valor);

        $registro = $modelo::whereRaw("LOWER(REPLACE(REPLACE(REPLACE(\"{$columna}\", '  ', ' '), '.', ''), '-', '')) = ?", [$valorNormalizado])
            ->when($comercioId, function ($q) use ($comercioId) {
                $q->where('comercio_id', $comercioId);
            })
            ->first();

        if (!$registro) {
            $extra[$columna] = $valor;
            if ($comercioId) {
                $extra['comercio_id'] = $comercioId;
            }
            if (in_array('slug', (new $modelo)->getFillable()) && empty($extra['slug'])) {
                $base = Str::slug($valor);
                $existe = $modelo::where('slug', $base)->exists();
                $extra['slug'] = $existe ? $base . '-' . Str::random(5) : $base;
            }
            try {
                $registro = $modelo::create($extra);
            } catch (\Illuminate\Database\QueryException $e) {
                Log::warning("buscarOCrearReferencia: no se pudo crear registro", [
                    'modelo' => $modelo,
                    'valor' => $valor,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return $registro->id;
    }

    private function obtenerMapeoHeadersImportacion(): array
    {
        return [
            'nombre' => 'nombre',
            'código de barras' => 'codigo_barras', 'codigo de barras' => 'codigo_barras', 'codigo_barras' => 'codigo_barras',
            'plu' => 'plu', 'código interno' => 'plu',
            'categoría' => 'categoria', 'categoria' => 'categoria', 'category' => 'categoria',
            'marca' => 'marca', 'brand' => 'marca',
            'proveedor' => 'proveedor', 'supplier' => 'proveedor',
            'precio costo' => 'precio_costo', 'precio_costo' => 'precio_costo', 'cost price' => 'precio_costo', 'costo' => 'precio_costo',
            'precio venta' => 'precio_venta', 'precio_venta' => 'precio_venta', 'sale price' => 'precio_venta', 'venta' => 'precio_venta',
            'stock mínimo' => 'stock_minimo', 'stock_minimo' => 'stock_minimo', 'stock minimo' => 'stock_minimo', 'min stock' => 'stock_minimo',
            'unidad' => 'unidad_medida', 'unidad_medida' => 'unidad_medida', 'unit' => 'unidad_medida',
            'unidad compra' => 'unidad_compra', 'unidad_compra' => 'unidad_compra', 'purchase unit' => 'unidad_compra',
            'cant. por compra' => 'cantidad_por_compra', 'cantidad_por_compra' => 'cantidad_por_compra', 'cantidad por compra' => 'cantidad_por_compra',
            'descripción' => 'descripcion', 'descripcion' => 'descripcion', 'description' => 'descripcion',
            'retornable' => 'es_retornable', 'es_retornable' => 'es_retornable', 'returnable' => 'es_retornable',
            'estado' => 'estado', 'status' => 'estado', 'active' => 'estado', 'activo' => 'estado',
        ];
    }

    private function tieneColumnaIdentificadorAlternativo(array $mappedHeaders): bool
    {
        return in_array('plu', $mappedHeaders) || in_array('sku', $mappedHeaders);
    }

    private function normalizarString(string $valor): string
    {
        $valor = str_replace("\0", '', $valor);
        $valor = trim(mb_strtolower($valor));
        $valor = preg_replace('/\s+/', ' ', $valor);
        return $valor;
    }

    private function normalizarPrecioArgentino($valor): ?float
    {
        if ($valor === null || $valor === '') return null;
        $valor = (string) $valor;
        $valor = str_replace(['$', 'USD', 'U\$S', 'ARS'], '', $valor);
        $valor = trim($valor);
        if ($valor === '') return null;
        $valor = rtrim($valor, '-');
        $valor = str_replace(',', '.', $valor);
        $valor = preg_replace('/\.(?=.*\.)/', '', $valor);
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        return null;
    }

    private function normalizarUnidadMedida($valor): string
    {
        $valor = strtolower(trim((string) $valor));
        $map = ['unidad' => 'Unidad', 'unid' => 'Unidad', 'un' => 'Unidad', 'kg' => 'Kg', 'kilo' => 'Kg', 'kilos' => 'Kg', 'gramos' => 'Gramos', 'g' => 'Gramos', 'gr' => 'Gramos', 'litro' => 'Litros', 'l' => 'Litros', 'lt' => 'Litros', 'litros' => 'Litros'];
        return $map[$valor] ?? 'Unidad';
    }

    private function parsearBooleano($valor, bool $default = false): bool
    {
        if ($valor === null || $valor === '') return $default;
        $valor = strtolower(trim((string) $valor));
        return in_array($valor, ['1', 'sí', 'si', 'true', 'activo', 'yes', 'verdadero']);
    }

    private function obtenerMapaAcentos(): array
    {
        return ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u'];
    }

    private function celdaAString($value): string
    {
        if ($value === null) return '';
        if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $value = $value->getPlainText();
        }
        return trim((string) $value);
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

    public function etiquetas(Request $request)
    {
        $request->validate([
            'modo' => 'required|in:todos,categoria,marca,busqueda',
            'categoria_id' => 'required_if:modo,categoria|exists:categorias,id',
            'marca_id' => 'required_if:modo,marca|exists:marcas,id',
            'busqueda' => 'required_if:modo,busqueda|string|max:255',
            'copias' => 'required|integer|min:1|max:50',
        ]);

        $query = Producto::query()->select(['id', 'nombre', 'precio_venta']);

        switch ($request->modo) {
            case 'todos':
                break;
            case 'categoria':
                $query->where('categoria_id', $request->categoria_id);
                break;
            case 'marca':
                $query->where('marca_id', $request->marca_id);
                break;
            case 'busqueda':
                $query->where(function ($q) use ($request) {
                    $q->where('nombre', 'ilike', "%{$request->busqueda}%")
                      ->orWhere('codigo_barras', 'ilike', "%{$request->busqueda}%");
                });
                break;
        }

        $productos = $query->orderBy('nombre')->get();

        if ($productos->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'No se encontraron productos para las opciones seleccionadas.']);
        }

        $productosRepetidos = $productos->flatMap(fn ($p) => collect(array_fill(0, $request->copias, null))->map(fn () => $p));

        $pdf = Pdf::loadView('pdf.etiquetas', [
            'productos' => $productosRepetidos,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('etiquetas_' . now()->format('Ymd_His') . '.pdf');
    }
}