<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    public function index()
    {
        return Inertia::render('Proveedores/Index', [
            'proveedores' => Proveedor::orderBy('id', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validados = $request->validate([
            'razon_social' => 'required|string|max:255',
            'cuit'         => 'required|string|max:15|unique:proveedores,cuit',
            'telefono'     => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'direccion'    => 'nullable|string|max:255',
        ]);

        $validados['estado'] = true;
        Proveedor::create($validados);

        return redirect()->back()->with('success', 'Proveedor registrado.');
    }

    public function update(Request $request, Proveedor $proveedore) // Laravel pluraliza raro a veces, usamos $proveedore
    {
        $validados = $request->validate([
            'razon_social' => 'required|string|max:255',
            'cuit'         => ['required', 'string', 'max:15', Rule::unique('proveedores')->ignore($proveedore->id)],
            'telefono'     => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'direccion'    => 'nullable|string|max:255',
        ]);

        $proveedore->update($validados);

        return redirect()->back()->with('success', 'Proveedor actualizado.');
    }

    public function status(Proveedor $proveedore)
    {
        $proveedore->update(['estado' => !$proveedore->estado]);
        return redirect()->back();
    }
}