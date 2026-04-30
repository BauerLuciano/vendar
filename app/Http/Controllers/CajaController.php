<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class CajaController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // 1. Verificamos si es un "Jefe" (SuperAdmin o Admin Global)
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);
        
        $query = Caja::with('sucursal');
        
        // Si NO es jefe y tiene sucursal, solo ve las cajas de su sucursal
        if (!$esJefe && $user->branch_id) {
            $query->where('sucursal_id', $user->branch_id);
        }
        $cajas = $query->orderBy('estado', 'desc')->orderBy('id', 'desc')->get();   
             
        $sucursales = $esJefe 
            ? Sucursal::where('tipo', 'punto_de_venta')->get() 
            : Sucursal::where('id', $user->branch_id)->where('tipo', 'punto_de_venta')->get();

        return Inertia::render('Cajas/Index', [
            'cajas' => $cajas,
            'sucursales' => $sucursales
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'sucursal_id' => [
                'required',
                // Validamos que exista y que además sea un punto de venta
                Rule::exists('sucursales', 'id')->where(function ($query) {
                    return $query->where('tipo', 'punto_de_venta');
                }),
            ],
        ], [
            'sucursal_id.exists' => 'La sucursal seleccionada no es válida o es un depósito.' 
        ]);

        Caja::create($validated);
        return redirect()->back()->with('success', 'Caja creada correctamente.');
    }

    public function update(Request $request, Caja $caja)
    {
        $validated = $request->validate(['nombre' => 'required|string|max:255']);
        $caja->update($validated);
        return redirect()->back()->with('success', 'Caja actualizada correctamente.');
    }

    public function toggleEstado(Caja $caja)
    {
        $caja->update(['estado' => !$caja->estado]);
        $mensaje = $caja->estado ? 'reactivada' : 'inactivada';
        
        return redirect()->back()->with('success', "Caja {$mensaje} correctamente.");
    }

    public function destroy(Caja $caja)
    {
        if (!$caja->puedeEliminarse()) {
            return redirect()->back()->with('error', 'No se puede eliminar esta caja porque tiene historial de turnos asociados. Por favor, utiliza la opción de inactivarla.');
        }

        $caja->delete();
        return redirect()->back()->with('success', 'Caja eliminada correctamente.');
    }
}