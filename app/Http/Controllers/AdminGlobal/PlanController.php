<?php

namespace App\Http\Controllers\AdminGlobal;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        $planes = Plan::orderBy('orden')->orderBy('precio_mensual')->get();

        $modulosDisponibles = [
            ['id' => 'pos', 'nombre' => 'Punto de Venta Base'],
            ['id' => 'lotes', 'nombre' => 'Gestión de Stock Avanzada (Lotes)'],
            ['id' => 'fiados', 'nombre' => 'Cuentas Corrientes (Fiados)'],
            ['id' => 'proveedores', 'nombre' => 'Gestión de Proveedores'],
            ['id' => 'auditoria', 'nombre' => 'Auditoría de Caja y Stock'],
            ['id' => 'transferencias', 'nombre' => 'Optimización de Stock (Sugerencias)'],
        ];

        return Inertia::render('AdminGlobal/Planes/Index', [
            'planes' => $planes,
            'modulosDisponibles' => $modulosDisponibles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:planes,slug',
            'descripcion' => 'nullable|string|max:1000',
            'precio_mensual' => 'required|numeric|min:0',
            'modulos' => 'required|array',
            'sucursales_limit' => 'required|integer|min:0',
            'usuarios_limit' => 'required|integer|min:0',
            'destacado' => 'boolean',
            'orden' => 'required|integer|min:0',
            'activo' => 'boolean',
        ]);

        $validated['modulos'] = array_merge(
            array_fill_keys(['pos', 'lotes', 'fiados', 'proveedores', 'auditoria', 'transferencias'], false),
            $validated['modulos']
        );

        Plan::create($validated);

        return redirect()->back()->with('exito', 'Plan creado correctamente.');
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:planes,slug,' . $plan->id,
            'descripcion' => 'nullable|string|max:1000',
            'precio_mensual' => 'required|numeric|min:0',
            'modulos' => 'required|array',
            'sucursales_limit' => 'required|integer|min:0',
            'usuarios_limit' => 'required|integer|min:0',
            'destacado' => 'boolean',
            'orden' => 'required|integer|min:0',
            'activo' => 'boolean',
        ]);

        $validated['modulos'] = array_merge(
            array_fill_keys(['pos', 'lotes', 'fiados', 'proveedores', 'auditoria', 'transferencias'], false),
            $validated['modulos']
        );

        $plan->update($validated);

        return redirect()->back()->with('exito', 'Plan actualizado correctamente.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->comercios()->exists()) {
            return redirect()->back()->withErrors(['error' => 'No se puede eliminar un plan que tiene comercios activos. Reasignalos primero.']);
        }

        $plan->delete();

        return redirect()->back()->with('exito', 'Plan eliminado.');
    }
}
