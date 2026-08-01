<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Producto;
use App\Models\StoreConfig;
use App\Models\Sucursal;
use App\Services\Promotion\PromotionEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TiendaController extends Controller
{
    public function __construct(
        private readonly PromotionEngineService $engine,
    ) {}

    public function __invoke($slug)
    {
        $comercio = Comercio::where('slug', $slug)->firstOrFail();

        session(['ultima_tienda_slug' => $slug]);
        session(['comercio_id_actual' => $comercio->id]);

        $consumidor = auth('consumidor')->user();

        $sucursales = Sucursal::where('comercio_id', $comercio->id)
            ->where('estado', true)
            ->select('id', 'nombre', 'latitud', 'longitud', 'direccion', 'costo_delivery')
            ->get()
            ->map(function ($sucursal) {
                $sucursal->latitud  = (float) $sucursal->latitud;
                $sucursal->longitud = (float) $sucursal->longitud;
                return $sucursal;
            });

        $categorias = Categoria::where('estado', true)
            ->where(function ($q) use ($comercio) {
                $q->where('comercio_id', $comercio->id)
                  ->orWhereNull('comercio_id');
            })
            ->orderBy('nombreCategoria')
            ->get()
            ->map(fn($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombreCategoria,
            ]);

        $storeConfig = Cache::remember("store_config_{$comercio->id}", 3600, function () use ($comercio) {
            return optional($comercio->storeConfig)->config ?? StoreConfig::defaultConfig();
        });

        return Inertia::render('Welcome', [
            'comercio'             => $comercio,
            'sucursalesBackend'    => $sucursales,
            'categorias'           => $categorias,
            'tienda_slug'          => $slug,
            'consumidorLogueado'   => $consumidor ? [
                'id'       => $consumidor->id,
                'nombre'   => $consumidor->nombre,
                'apellido' => $consumidor->apellido,
                'email'    => $consumidor->email,
                'telefono' => $consumidor->telefono,
            ] : null,
            'geoapifyKey'          => config('services.geoapify.key'),
            'storeConfig'          => $storeConfig,
            'pedidoExitoso'        => request()->boolean('pedido_exitoso'),
            'pedidoId'             => request('pedido_id'),
            'mpPaymentId'          => request('payment_id'),
        ]);
    }

    public function catalogo(Request $request, $sucursal_id)
    {
        $request->merge(['sucursal_id' => $sucursal_id]);

        $validated = $request->validate([
            'sucursal_id'  => 'required|exists:sucursales,id',
            'per_page'     => 'nullable|integer|min:1|max:200',
            'busqueda'     => 'nullable|string|max:100',
            'categoria_id' => 'nullable|string|max:10',
            'sort_by'      => 'nullable|string|in:nombre,precio_venta',
            'sort_order'   => 'nullable|string|in:asc,desc',
            'page'         => 'nullable|integer|min:1',
        ]);

        $sucursal = Sucursal::findOrFail($validated['sucursal_id']);
        $comercioId = $sucursal->comercio_id;
        $perPage = $validated['per_page'] ?? 200;
        $busqueda = $validated['busqueda'] ?? null;
        $categoriaId = $validated['categoria_id'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'nombre';
        $sortOrder = $validated['sort_order'] ?? 'asc';

        $query = $sucursal->productos()
            ->with('categoria')
            ->with('marca')
            ->with('globalProduct')
            ->where('productos.estado', true);

        if ($busqueda) {
            $query->where('productos.nombre', 'ilike', "%{$busqueda}%");
        }

        if ($categoriaId && $categoriaId !== 'todas') {
            $query->where('productos.categoria_id', (int) $categoriaId);
        }

        $allowedSort = ['nombre', 'precio_venta'];
        $sortBy = in_array($sortBy, $allowedSort) ? $sortBy : 'nombre';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
        $query->orderBy("productos.{$sortBy}", $sortOrder);

        $productos = $query->paginate(min($perPage, 200), ['*'], 'page', (int) $request->input('page', 1));

        $results = $this->engine->forProducts(
            collect($productos->items()),
            $comercioId,
        );

        $resultByProductoId = [];
        foreach ($results as $r) {
            $resultByProductoId[$r['producto']->id] = $r['promotion_result'];
        }

        $mapped = collect($productos->items())->map(function ($prod) use ($resultByProductoId) {
            $pivot = $prod->pivot;
            $cantidad_fisica = $pivot?->cantidad_fisica ?? 0;
            $cantidad_reservada = $pivot?->cantidad_reservada ?? 0;
            $stock_disponible = max(0, $cantidad_fisica - $cantidad_reservada);

            $promoResult = $resultByProductoId[$prod->id] ?? null;
            $promo = $promoResult?->bestPromotion;

            return [
                'id'           => $prod->id,
                'nombre'       => $prod->nombre,
                'descripcion'  => $prod->descripcion,
                'categoria_id' => $prod->categoria_id,
                'categoria'    => $prod->categoria
                    ? ['id' => $prod->categoria->id, 'nombre' => $prod->categoria->nombreCategoria]
                    : null,
                'marca'       => $prod->marca ? ['id' => $prod->marca->id, 'nombre' => $prod->marca->nombre] : null,
                'precio'      => (float) $prod->precio_venta,
                'imagen_url'  => $prod->url_imagen,
                'stock'       => $stock_disponible,
                'promotion'   => $promo ? [
                    'active'           => true,
                    'label'            => $promo->discountLabel ?? 'Promoción',
                    'original_price'   => $promo->originalPrice,
                    'final_price'      => $promo->finalPrice,
                    'discount_amount'  => $promo->discountAmount,
                    'discount_percent' => $promo->originalPrice > 0
                        ? round(($promo->discountAmount / $promo->originalPrice) * 100)
                        : null,
                    'ends_at'          => $promo->promotion->endsAt,
                ] : null,
            ];
        });

        $countsPorCategoria = Producto::join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->where('producto_sucursal.sucursal_id', $sucursal_id)
            ->where('productos.estado', true)
            ->whereNotNull('productos.categoria_id')
            ->selectRaw('categoria_id, count(*) as total')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id')
            ->toArray();

        return response()->json([
            'data' => $mapped->values()->all(),
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page'    => $productos->lastPage(),
                'per_page'     => $productos->perPage(),
                'total'        => $productos->total(),
            ],
            'counts_por_categoria' => $countsPorCategoria,
        ])->header('Cache-Control', 'public, max-age=30');
    }

    public function promociones($sucursal_id)
    {
        $sucursal = Sucursal::findOrFail($sucursal_id);

        $comercioId = $sucursal->comercio_id;

        $productos = $sucursal->productos()
            ->with('categoria')
            ->with('globalProduct')
            ->where('productos.estado', true)
            ->wherePivot('cantidad_fisica', '>', 0)
            ->where(function ($q) {
                $q->whereNull('producto_sucursal.cantidad_reservada')
                  ->orWhereColumn('producto_sucursal.cantidad_fisica', '>', 'producto_sucursal.cantidad_reservada');
            })
            ->limit(50)
            ->get();

        $results = $this->engine->forProducts($productos, $comercioId);

        $mapped = collect();
        foreach ($results as $r) {
            $promoResult = $r['promotion_result'];
            $promo = $promoResult->bestPromotion;
            if ($promo === null) continue;

            $prod = $r['producto'];
            $pivot = $prod->pivot;
            $stockDisponible = max(0, ($pivot?->cantidad_fisica ?? 0) - ($pivot?->cantidad_reservada ?? 0));

            $mapped->push([
                'id'                => $prod->id,
                'nombre'            => $prod->nombre,
                'precio'            => (float) $prod->precio_venta,
                'stock'             => $stockDisponible,
                'imagen_url'        => $prod->url_imagen,
                'categoria'         => $prod->categoria?->nombreCategoria,
                'promotion' => [
                    'active'           => true,
                    'label'            => $promo->discountLabel ?? 'Promoción',
                    'original_price'   => $promo->originalPrice,
                    'final_price'      => $promo->finalPrice,
                    'discount_amount'  => $promo->discountAmount,
                    'discount_percent' => $promo->originalPrice > 0
                        ? round(($promo->discountAmount / $promo->originalPrice) * 100)
                        : null,
                    'ends_at'          => $promo->promotion->endsAt,
                ],
            ]);
        }

        return response()->json(['data' => $mapped->values()->all()])
            ->header('Cache-Control', 'public, max-age=30');
    }
}
