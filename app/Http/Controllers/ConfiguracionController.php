<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return Inertia::render('Configuracion/Index', [
            'configuraciones' => $configuraciones
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->except(['logo_empresa', 'logo_url']);

        // 1. Guardar todos los textos, números y booleanos
        foreach ($data as $clave => $valor) {
            // 🔥 MEJORA: Usamos updateOrCreate para que cree la fila si no existe
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
        }

        // 2. Guardar el Logo de la empresa
        if ($request->hasFile('logo_empresa')) {
            $logoViejo = Configuracion::where('clave', 'logo_empresa')->value('valor');
            
            if ($logoViejo && Storage::disk('public')->exists($logoViejo)) {
                Storage::disk('public')->delete($logoViejo);
            }

            $path = $request->file('logo_empresa')->store('logos', 'public');
            
            Configuracion::updateOrCreate(
                ['clave' => 'logo_empresa'],
                ['valor' => $path]
            );
        }

        return redirect()->back()->with('success', 'Configuraciones actualizadas con éxito.');
    }
}