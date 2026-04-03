<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductoController extends Controller
{
    /**
     * Muestra el listado de productos y envía datos para los modales.
     */
    public function index()
    {
        return Inertia::render('Productos/Index', [
            // Cargamos relaciones para ver nombres de categoría y marca en la tabla
            'productos' => Producto::with(['categoria', 'marca'])->orderBy('id', 'desc')->get(),
            // Listas para los desplegables (Selects)
            'categorias' => Categoria::all(), 
            'marcas' => Marca::all(),         
        ]);
    }

    /**
     * Guarda un nuevo producto en la base de datos.
     */
    public function store(Request $request)
    {
        $validados = $request->validate([
            'nombre'       => 'required|string|max:255',
            'sku'          => 'required|string|max:13|unique:productos,sku',
            'categoria_id' => 'required|exists:categorias,id',
            'marca_id'     => 'required|exists:marcas,id',
            'precio_costo' => 'required|numeric',
            'precio_venta' => 'required|numeric',
            'stock_minimo' => 'required|integer',
            'descripcion'  => 'nullable|string',
            'imagen'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', 
        ]);

        if ($request->hasFile('imagen')) {
            $ruta = $request->file('imagen')->store('productos', 'public');
            $validados['imagen'] = $ruta;
        }

        $validados['estado'] = true; // Todo producto nuevo entra activo

        Producto::create($validados);

        return redirect()->back();
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Request $request, Producto $producto)
    {
        $validados = $request->validate([
            'nombre'       => 'required|string|max:255',
            'sku'          => 'required|string|max:13|unique:productos,sku,' . $producto->id,
            'categoria_id' => 'required|exists:categorias,id',
            'marca_id'     => 'required|exists:marcas,id',
            'precio_costo' => 'required|numeric',
            'precio_venta' => 'required|numeric',
            'stock_minimo' => 'required|integer',
            'descripcion'  => 'nullable|string',
            'imagen'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $ruta = $request->file('imagen')->store('productos', 'public');
            $validados['imagen'] = $ruta;
        } else {
            unset($validados['imagen']);
        }

        $producto->update($validados);

        return redirect()->back();
    }

    /**
     * Cambia el estado del producto (Baja Lógica / Reactivación).
     */
    public function status(Producto $producto)
    {
        $producto->update([
            'estado' => !$producto->estado
        ]);

        return redirect()->back();
    }
}