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
        // 🚀 MAGIA: pluck convierte toda la tabla en un array clave => valor
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return Inertia::render('Configuracion/Index', [
            'configuraciones' => $configuraciones
        ]);
    }

    public function update(Request $request)
    {
        // Sacamos el logo y la URL para no procesarlos en el bucle normal
        $data = $request->except(['logo_empresa', 'logo_url']);

        // 1. Guardar todos los textos, números y booleanos
        foreach ($data as $clave => $valor) {
            Configuracion::where('clave', $clave)->update(['valor' => $valor]);
        }

        // 2. Guardar el Logo de la empresa (Si subieron uno nuevo)
        if ($request->hasFile('logo_empresa')) {
            
            // Borramos el logo viejo para no llenar el servidor de basura
            $logoViejo = Configuracion::getValor('logo_empresa');
            if ($logoViejo && Storage::disk('public')->exists($logoViejo)) {
                Storage::disk('public')->delete($logoViejo);
            }

            // Guardamos el nuevo
            $path = $request->file('logo_empresa')->store('logos', 'public');
            Configuracion::where('clave', 'logo_empresa')->update(['valor' => $path]);
        }

        return redirect()->back()->with('success', 'Configuraciones actualizadas con éxito.');
    }
}