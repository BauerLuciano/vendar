<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    public function enter(Comercio $comercio)
    {
        // 🔥 Buscamos la primera sucursal que le pertenezca a este comercio
        $sucursal = Sucursal::where('comercio_id', $comercio->id)->first();

        // Si el comercio es nuevo y todavía no tiene sucursales creadas, frenamos de forma limpia
        if (!$sucursal) {
            return redirect()->back()->with('error', "No se puede ingresar: El comercio '{$comercio->nombre}' no tiene ninguna sucursal asignada.");
        }

        $user = auth()->user();

        // 1. Guardamos tu sucursal original como miga de pan en la sesión
        if (!session()->has('admin_comercio_original_id')) {
            session()->put('admin_comercio_original_id', $user->branch_id);
        }

        // 2. Te asignamos el ID de la SUCURSAL real (no del comercio)
        $user->branch_id = $sucursal->id;
        $user->save();

        // 3. Salto exitoso al sistema del cliente
        return redirect()->route('dashboard')->with('exito', "¡Modo Dios activado en: {$sucursal->nombre}!");
    }

    public function leave()
    {
        $user = auth()->user();

        // 1. Te devolvemos a tu sucursal original
        if (session()->has('admin_comercio_original_id')) {
            $user->branch_id = session()->pull('admin_comercio_original_id');
            $user->save();
        }

        // 2. Te devolvemos a tu panel SaaS Global
        return redirect()->route('admin.comercios.index')->with('exito', 'Saliste del Modo Dios.');
    }
}