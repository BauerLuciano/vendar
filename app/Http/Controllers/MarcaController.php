<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MarcaController extends Controller
{
    public function index()
    {
        return Inertia::render('Marcas/Index', [
            'marcas' => Marca::orderBy('id', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validados = $request->validate([
            'nombreMarca' => 'required|string|max:255|unique:marcas,nombreMarca',
            'imagen'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('imagen')) {
            $validados['imagen'] = $request->file('imagen')->store('marcas', 'public');
        }

        $validados['slug'] = Str::slug($request->nombreMarca);
        $validados['estado'] = true;

        Marca::create($validados);
        return redirect()->back();
    }

    public function update(Request $request, Marca $marca)
    {
        $validados = $request->validate([
            'nombreMarca' => 'required|string|max:255|unique:marcas,nombreMarca,' . $marca->id,
            'imagen'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('imagen')) {
            if ($marca->imagen) { Storage::disk('public')->delete($marca->imagen); }
            $validados['imagen'] = $request->file('imagen')->store('marcas', 'public');
        }

        $validados['slug'] = Str::slug($request->nombreMarca);
        $marca->update($validados);

        return redirect()->back();
    }

    public function status(Marca $marca)
    {
        $marca->update(['estado' => !$marca->estado]);
        return redirect()->back();
    }
}