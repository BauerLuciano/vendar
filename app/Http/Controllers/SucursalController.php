<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SucursalController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $estado = $request->input('estado', 'all');

        $sucursales = Sucursal::when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('direccion', 'LIKE', "%{$search}%")
                        ->orWhere('id', 'LIKE', "%{$search}%");
                });
            })
            ->when($estado !== 'all', function ($q) use ($estado) {
                $q->where('estado', $estado === 'activas' ? true : false);
            })
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Sucursales/Index', [
            'sucursales' => $sucursales,
            'filtros' => $request->only(['search', 'estado'])
        ]);
    }

    /**
     * Almacena una nueva sucursal validando el límite del plan SaaS
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Buscamos la sucursal actual del usuario para saber a qué Comercio (tenant) pertenece
        $sucursalActual = \App\Models\Sucursal::with('comercio')->find($user->branch_id);
        $comercio = $sucursalActual?->comercio;

        // 🔥 1. EL PATOVICA DEL LÍMITE DE SUCURSALES
        if ($comercio) {
            $cantidadActual = \App\Models\Sucursal::where('comercio_id', $comercio->id)->count();

            if ($cantidadActual >= $comercio->limite_sucursales) {
                return redirect()->back()->with('error', "🔒 Límite alcanzado: Tu plan actual solo te permite registrar hasta {$comercio->limite_sucursales} sucursal(es). ¡Comunicate con Ventas para expandir tu negocio!");
            }
        }

        // 2. VALIDACIÓN DE LOS DATOS DEL FORMULARIO
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'tipo' => 'nullable|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ]);

        // 🔥 3. ASIGNACIÓN AUTOMÁTICA AL DUEÑO (TENANT)
        if ($comercio) {
            $validated['comercio_id'] = $comercio->id;
        }
        
        $validated['estado'] = true; // Nace activa por defecto

        \App\Models\Sucursal::create($validated);

        return redirect()->back()->with('exito', 'Nueva sucursal registrada con éxito.');
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $validados = $request->validate([
            'nombre'    => 'required|string|max:100',
            'direccion' => 'required|string|max:255',
            'telefono'  => 'nullable|string|max:15|regex:/^\d+$/',
            'tipo'      => 'required|in:punto_de_venta,deposito',
        ], [
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'tipo.in' => 'El tipo de local no es válido.',
        ]);

        $sucursal->update($validados);
        
        return redirect()->back()->with('success', 'Sucursal actualizada exitosamente.');
    }

    public function status(Sucursal $sucursal)
    {
        $sucursal->update(['estado' => !$sucursal->estado]);
        return redirect()->back()->with('success', 'Estado de la sucursal modificado.');
    }
}