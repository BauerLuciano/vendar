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

    public function store(Request $request)
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

        $validados['estado'] = true;

        Sucursal::create($validados);
        
        return redirect()->back()->with('success', 'Sucursal creada exitosamente.');
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