<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IngresoMercaderiaController extends Controller
{
    /**
     * Procesa la entrada de mercadería y detecta inflación.
     */
    public function store(Request $request)
    {
        $alertasInflacion = [];

        DB::transaction(function () use ($request, &$alertasInflacion) {
            foreach ($request->items as $item) {
                $producto = Producto::findOrFail($item['producto_id']);
                $costoAnterior = $producto->precio_costo;
                $nuevoCosto = $item['nuevo_costo'];

                // Si el costo aumentó, calculamos el nuevo precio sugerido
                if ($nuevoCosto > $costoAnterior) {
                    // Si no tiene margen definido, usamos 0 para no romper nada
                    $margen = $producto->margen_ganancia ?? 0;
                    $nuevoPrecioVenta = $nuevoCosto * (1 + ($margen / 100));

                    $alertasInflacion[] = [
                        'producto_id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'costo_anterior' => $costoAnterior,
                        'nuevo_costo' => $nuevoCosto,
                        'precio_actual' => $producto->precio_venta,
                        'precio_sugerido' => round($nuevoPrecioVenta, 2),
                    ];
                }

                // Actualizamos el costo base siempre
                $producto->update(['precio_costo' => $nuevoCosto]);
                
                // NOTA: Acá faltaría sumar el stock a la tabla branch_producto, 
                // pero lo vemos cuando armemos la pantalla de ingresos.
            }
        });

        // Devolvemos las alertas a la sesión para que Vue las muestre en un Modal
        return redirect()->back()->with('alertas_inflacion', $alertasInflacion);
    }
}