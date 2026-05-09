<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalAdminController extends Controller
{
    /**
     * Lista todos los comercios (Kioscos/Mercados) registrados en el sistema
     */
    public function index()
    {
        return Inertia::render('AdminGlobal/Comercios/Index', [
            'comercios' => Comercio::all(),
            // Definimos los módulos disponibles en el sistema para poder tildarlos
            'modulosDisponibles' => [
                ['id' => 'pos', 'nombre' => 'Punto de Venta Base'],
                ['id' => 'inventario', 'nombre' => 'Gestión de Stock Avanzada'],
                ['id' => 'cuentas_corrientes', 'nombre' => 'Cuentas Corrientes (Fiados)'],
                ['id' => 'proveedores', 'nombre' => 'Gestión de Proveedores'],
                ['id' => 'auditoria', 'nombre' => 'Auditoría de Caja y Stock'],
            ]
        ]);
    }

    /**
     * Crea un nuevo cliente (Comercio)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'plan' => 'required|in:basico,pro,premium',
            'status' => 'required|in:activo,suspendido,trial',
            'limite_sucursales' => 'required|integer|min:1',
            'vencimiento_pago' => 'nullable|date',
            'modulos_habilitados' => 'nullable|array',
        ]);

        // Si no mandaron módulos, le asignamos el POS por defecto
        if (empty($validated['modulos_habilitados'])) {
            $validated['modulos_habilitados'] = ['pos' => true];
        }
        
        $validated['slug'] = str($request->nombre)->slug();

        Comercio::create($validated);

        return redirect()->back()->with('exito', 'Comercio registrado con éxito.');
    }

    /**
     * La función "mágica": Actualiza qué puede hacer el comercio y su estado
     */
    public function update(Request $request, Comercio $comercio)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'plan' => 'required|in:basico,pro,premium',
            'status' => 'required|in:activo,suspendido,trial',
            'limite_sucursales' => 'required|integer|min:1',
            'vencimiento_pago' => 'nullable|date',
            'modulos_habilitados' => 'required|array',
        ]);

        // Si cambiaron el nombre, actualizamos el slug
        $validated['slug'] = str($request->nombre)->slug();

        $comercio->update($validated);

        return redirect()->back()->with('exito', 'Configuración del comercio actualizada.');
    }
}