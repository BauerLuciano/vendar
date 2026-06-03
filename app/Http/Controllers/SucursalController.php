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
        $sucursalActual = \App\Models\Sucursal::with('comercio')->find($user->branch_id);
        $comercio = $sucursalActual?->comercio;

        if ($comercio) {
            $cantidadActual = \App\Models\Sucursal::where('comercio_id', $comercio->id)->count();
            if ($cantidadActual >= $comercio->limite_sucursales) {
                return redirect()->back()->with('error', "🔒 Límite alcanzado...");
            }
        }

        // 2. VALIDACIÓN (Agregamos costo_delivery)
        $validated = $request->validate([
            'nombre'         => 'required|string|max:255',
            'direccion'      => 'required|string|max:255',
            'telefono'       => 'nullable|string|max:255',
            'tipo'           => 'required|in:punto_de_venta,deposito',
            'latitud'        => 'required|numeric',
            'longitud'       => 'required|numeric',
            'costo_delivery' => 'nullable|numeric|min:0', // 🔥 Nuevo campo
        ]);

        if ($comercio) {
            $validated['comercio_id'] = $comercio->id;
        }
        
        $validated['estado'] = true;

        \App\Models\Sucursal::create($validated);

        return redirect()->back()->with('exito', 'Nueva sucursal registrada con éxito.');
    }

   public function update(Request $request, Sucursal $sucursal)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $sucursal->comercio_id !== $comercioId) {
            abort(403, 'Esta sucursal no pertenece a tu comercio.');
        }

        $validados = $request->validate([
            'nombre'         => 'required|string|max:100',
            'direccion'      => 'required|string|max:255',
            'telefono'       => 'nullable|string|max:15|regex:/^\d+$/',
            'tipo'           => 'required|in:punto_de_venta,deposito',
            // 🔥 AGREGAMOS ESTOS 3 CAMPOS QUE FALTABAN:
            'latitud'        => 'required|numeric',
            'longitud'       => 'required|numeric',
            'costo_delivery' => 'nullable|numeric|min:0',
        ], [
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'tipo.in'        => 'El tipo de local no es válido.',
        ]);

        // Ahora $validados ya tiene la latitud y longitud, y se guardarán en la DB
        $sucursal->update($validados);
        
        return redirect()->back()->with('success', 'Sucursal actualizada exitosamente.');
    }

    public function status(Sucursal $sucursal)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $sucursal->comercio_id !== $comercioId) {
            abort(403, 'Esta sucursal no pertenece a tu comercio.');
        }

        $sucursal->update(['estado' => !$sucursal->estado]);
        return redirect()->back()->with('success', 'Estado de la sucursal modificado.');
    }
}