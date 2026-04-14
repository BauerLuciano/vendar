<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index()
    {
        return Inertia::render('Productos/Index', [
            'productos' => Producto::with(['categoria', 'marca', 'sucursales', 'proveedor'])->orderBy('id', 'desc')->get(),
            'categorias' => Categoria::all(), 
            'marcas' => Marca::all(),        
            'proveedores' => Proveedor::where('estado', true)->get(),
            'sucursales' => Sucursal::all()
        ]);
    }

    public function store(Request $request)
    {
        $validados = $request->validate([
            'nombre'         => 'required|string|max:255',
            'codigo_barras'  => 'required|string|min:2|max:14|regex:/^[0-9]+$/|unique:productos,codigo_barras',
            'categoria_id'   => 'required|exists:categorias,id',
            'marca_id'       => 'required|exists:marcas,id',
            'proveedor_id'   => 'required|exists:proveedores,id',
            'unidad_medida'  => 'required|in:Unidad,Kg',
            'es_retornable'  => 'boolean',
            'precio_costo'   => 'required|numeric|min:0',
            'precio_venta'   => 'required|numeric|min:0',
            'stock_minimo'   => 'required|numeric|min:0',
            'stock_inicial'  => 'nullable|numeric|min:0',
            'descripcion'    => 'nullable|string',
            'imagen'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072', 
        ], [
            'codigo_barras.regex' => 'El código de barras solo puede contener números.',
            'codigo_barras.min' => 'El código debe tener al menos 2 números.',
            'codigo_barras.max' => 'El código no puede superar los 14 números.',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('imagen')) {
                $validados['imagen'] = $request->file('imagen')->store('productos', 'public');
            }

            $validados['estado'] = true; 
            $producto = Producto::create($validados);

            $sucursalId = auth()->user()->sucursal_id ?? Sucursal::first()->id;
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
        $validados = $request->validate([
            'nombre'        => 'required|string|max:255',
            'codigo_barras' => ['required', 'string', 'min:2', 'max:14', 'regex:/^[0-9]+$/', Rule::unique('productos')->ignore($producto->id)],
            'categoria_id'  => 'required|exists:categorias,id',
            'marca_id'      => 'required|exists:marcas,id',
            'proveedor_id'  => 'required|exists:proveedores,id',
            'unidad_medida' => 'required|in:Unidad,Kg',
            'es_retornable' => 'boolean',
            'precio_costo'  => 'required|numeric|min:0',
            'precio_venta'  => 'required|numeric|min:0',
            'stock_minimo'  => 'required|numeric|min:0',
            'descripcion'   => 'nullable|string',
            'imagen'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ], [
            'codigo_barras.regex' => 'El código de barras solo puede contener números.',
        ]);

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $validados['imagen'] = $request->file('imagen')->store('productos', 'public');
        } else {
            unset($validados['imagen']);
        }

        $producto->update($validados);

        return redirect()->back()->with('success', 'Producto actualizado correctamente.');
    }

    public function status(Producto $producto)
    {
        $producto->update(['estado' => !$producto->estado]);
        return redirect()->back()->with('success', 'Estado modificado.');
    }

    public function ajustarStock(Request $request, Producto $producto)
    {
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

    public function auditoria(Producto $producto)
    {
        $movimientos = DB::table('movimientos_stock')
            ->join('users', 'movimientos_stock.user_id', '=', 'users.id')
            ->join('sucursales', 'movimientos_stock.sucursal_id', '=', 'sucursales.id')
            ->where('producto_id', $producto->id)
            ->select('movimientos_stock.*', 'users.name as usuario', 'sucursales.nombre as sucursal')
            ->orderBy('movimientos_stock.created_at', 'desc')
            ->get();

        return response()->json($movimientos);
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