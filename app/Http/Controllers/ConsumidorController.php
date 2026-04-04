<?php

namespace App\Http\Controllers;

use App\Models\Consumidor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ConsumidorController extends Controller
{
    public function index()
    {
        return Inertia::render('Consumidores/Index', [
            // Los traemos ordenados de más nuevos a más viejos
            'consumidores' => Consumidor::orderBy('id', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'dni' => 'nullable|string|unique:consumidores,dni',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
        ]);

        Consumidor::create($validated);
        
        return redirect()->back()->with('success', 'Cliente registrado exitosamente.');
    }

    public function update(Request $request, Consumidor $consumidor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            // Acá le decimos que el DNI debe ser único, EXCEPTO para este mismo cliente
            'dni' => ['nullable', 'string', Rule::unique('consumidores')->ignore($consumidor->id)],
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
        ]);

        $consumidor->update($validated);
        
        return redirect()->back()->with('success', 'Datos del cliente actualizados.');
    }
}