<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ElegirSucursalController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $sucursales = $user->sucursales()->get();

        if ($sucursales->count() === 0 && $user->branch_id) {
            $sucursales = collect([$user->branch]);
        }

        return Inertia::render('Auth/ElegirSucursal', [
            'sucursales' => $sucursales,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
        ]);

        $user = $request->user();
        $tieneAcceso = $user->sucursales()->where('sucursal_id', $request->sucursal_id)->exists()
            || $user->branch_id == $request->sucursal_id;

        if (!$tieneAcceso) {
            return redirect()->back()->withErrors(['sucursal_id' => 'No tenés acceso a esa sucursal.']);
        }

        session(['sucursal_activa_id' => (int) $request->sucursal_id]);

        $redirect = $request->input('redirect', route('dashboard'));
        return redirect()->to($redirect);
    }
}
