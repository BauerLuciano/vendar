<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Comercio; // 🔥 No te olvides de importar el modelo Comercio
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        $user = $request->user();
        $comercio = Comercio::findOrFail(
            $user->comercio_id ?? $user->branch?->comercio_id
        );

        return Inertia::render('Configuracion/Index', [
            'configuraciones' => $configuraciones,
            'comercio' => $comercio // Se lo inyectamos a Vue
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $comercio = Comercio::findOrFail(
            $user->comercio_id ?? $user->branch?->comercio_id
        );
        
        // Filtramos del request SOLO las columnas que van en la tabla comercios
        // Actualizamos el comercio en la base de datos (campos seguros via fillable)
        $comercio->update($request->only([
            'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
            'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
            'acepta_efectivo',
        ]));
        // Campos sensibles: asignación explícita fuera de mass-assignment
        if ($request->has('mp_access_token')) {
            $comercio->mp_access_token = $request->mp_access_token;
        }
        if ($request->has('payway_public_key')) {
            $comercio->payway_public_key = $request->payway_public_key;
        }
        $comercio->save();

        // ====================================================================
        // 2. GUARDAR CONFIGURACIONES GLOBALES (Textos, números, booleanos)
        // ====================================================================
        $clavesComercio = [
            'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
            'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
            'acepta_efectivo', 'mp_access_token', 'payway_public_key',
        ];
        $dataGlobal = $request->except(array_merge(['logo_empresa', 'logo', 'logo_url'], $clavesComercio));

        foreach ($dataGlobal as $clave => $valor) {
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
        }

        // ====================================================================
        // 3. GUARDAR EL LOGO DE LA EMPRESA (en tabla comercios)
        // ====================================================================
        if ($request->hasFile('logo')) {
            if ($comercio->logo && Storage::disk('public')->exists($comercio->logo)) {
                Storage::disk('public')->delete($comercio->logo);
            }
            $comercio->logo = $request->file('logo')->store('logos', 'public');
            $comercio->save();
        }

        return redirect()->back()->with('success', 'Configuraciones actualizadas con éxito.');
    }
}