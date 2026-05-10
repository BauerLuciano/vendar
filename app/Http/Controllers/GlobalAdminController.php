<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Sucursal; // 🔥 Importamos el modelo Sucursal
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class GlobalAdminController extends Controller
{
    /**
     * Lista todos los comercios registrados
     */
    public function index()
    {
        return Inertia::render('AdminGlobal/Comercios/Index', [
            'comercios' => Comercio::all(),
            // 🔥 Actualizamos los IDs para que coincidan con los candados del Sidebar y web.php
            'modulosDisponibles' => [
                ['id' => 'pos', 'nombre' => 'Punto de Venta Base'],
                ['id' => 'lotes', 'nombre' => 'Gestión de Stock Avanzada (Lotes)'],
                ['id' => 'fiados', 'nombre' => 'Cuentas Corrientes (Fiados)'],
                ['id' => 'proveedores', 'nombre' => 'Gestión de Proveedores'],
                ['id' => 'auditoria', 'nombre' => 'Auditoría de Caja y Stock'],
                ['id' => 'transferencias', 'nombre' => 'Optimización de Stock (Sugerencias)'],
            ]
        ]);
    }

    /**
     * Crea un nuevo cliente (Comercio) y su sucursal por defecto
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
        
        $validated['slug'] = Str::slug($request->nombre);

        // 1. Creamos el Comercio
        $comercio = Comercio::create($validated);

        // 2. 🔥 MAGIA: Creamos la sucursal por defecto automáticamente
        // Usamos coordenadas de Posadas, Misiones como base (puedes cambiarlas)
        Sucursal::create([
            'comercio_id' => $comercio->id,
            'nombre'      => 'Casa Central',
            'direccion'   => 'Dirección a definir',
            'latitud'     => -27.367, 
            'longitud'    => -55.896,
            'estado'      => true, // Activa por defecto
        ]);

        return redirect()->back()->with('exito', 'Comercio y sucursal base registrados con éxito.');
    }

    /**
     * Actualiza la configuración del comercio
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

        $validated['slug'] = Str::slug($request->nombre);

        $comercio->update($validated);

        return redirect()->back()->with('exito', 'Configuración del comercio actualizada.');
    }
}