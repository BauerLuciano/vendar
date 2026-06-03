<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    public function enter(Comercio $comercio)
    {
        $sucursal = Sucursal::where('comercio_id', $comercio->id)->first();

        if (!$sucursal) {
            return redirect()->back()->with('error', "No se puede ingresar: El comercio '{$comercio->nombre}' no tiene ninguna sucursal asignada.");
        }

        $user = auth()->user();

        if (!session()->has('admin_comercio_original_id')) {
            session()->put('admin_comercio_original_id', $user->branch_id);
        }

        $user->branch_id = $sucursal->id;
        $user->save();

        activity()
            ->performedOn($comercio)
            ->causedBy($user)
            ->withProperties(['sucursal' => $sucursal->nombre])
            ->log('impersonate_enter');

        return redirect()->route('dashboard')->with('exito', "¡Modo Dios activado en: {$sucursal->nombre}!");
    }

    public function leave()
    {
        $user = auth()->user();

        if (session()->has('admin_comercio_original_id')) {
            $user->branch_id = session()->pull('admin_comercio_original_id');
            $user->save();
        } elseif ($user->branch?->comercio_id) {
            return redirect()->route('admin.comercios.index')
                ->withErrors(['error' => 'No se encontró la sesión de impersonación. Contactá al administrador.']);
        }

        activity()
            ->causedBy($user)
            ->log('impersonate_leave');

        return redirect()->route('admin.comercios.index')->with('exito', 'Saliste del Modo Dios.');
    }
}