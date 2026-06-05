<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TiendaController extends Controller
{
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
            ->orderBy('nombreCategoria')
            ->get()
            ->map(fn($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombreCategoria,
            ]);

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
        ]);
    }

    public function catalogo(Request $request, $sucursal_id)
    {
        $sucursal = Sucursal::find($sucursal_id);
        if (!$sucursal) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
            ]);
        }

        $perPage = (int) $request->input('per_page', 200);
        $busqueda = $request->input('busqueda');
        $categoriaId = $request->input('categoria_id');
        $sortBy = $request->input('sort_by', 'nombre');
        $sortOrder = $request->input('sort_order', 'asc');

        $query = $sucursal->productos()
            ->with('categoria')
            ->with('marca')
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

        $mapped = collect($productos->items())->map(function ($prod) use ($sucursal_id) {
            $pivot = $prod->sucursales()->where('sucursal_id', $sucursal_id)->first()?->pivot;
            $cantidad_fisica = $pivot?->cantidad_fisica ?? 0;
            $cantidad_reservada = $pivot?->cantidad_reservada ?? 0;
            $stock_disponible = max(0, $cantidad_fisica - $cantidad_reservada);

            return [
                'id'           => $prod->id,
                'nombre'       => $prod->nombre,
                'descripcion'  => $prod->descripcion,
                'categoria_id' => $prod->categoria_id,
                'categoria'    => $prod->categoria
                    ? ['id' => $prod->categoria->id, 'nombre' => $prod->categoria->nombreCategoria]
                    : null,
                'marca'        => $prod->marca ? ['id' => $prod->marca->id, 'nombre' => $prod->marca->nombre] : null,
                'precio'       => $prod->precio_venta,
                'imagen_url'   => $prod->url_imagen,
                'stock'        => $stock_disponible,
            ];
        });

        return response()->json([
            'data' => $mapped->values()->all(),
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page'    => $productos->lastPage(),
                'per_page'     => $productos->perPage(),
                'total'        => $productos->total(),
            ],
        ]);
    }
}
