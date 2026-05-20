<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Sucursal;
use Symfony\Component\HttpFoundation\Response;

class VerificarEstadoCuenta
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Dejamos pasar las rutas de autenticación, logout y la vista de suspensión
        if ($request->routeIs('login', 'logout', 'cuenta.suspendida', 'pending.approval', 'admin.comercios.*', 'impersonate.*')) {
            return $next($request);
        }

        $user = $request->user();

        // Si no está logueado o es el Admin Global maestro, pasa libre
        if (!$user || !$user->branch_id || (method_exists($user, 'hasRole') && $user->hasRole('Administrador Global'))) {
            return $next($request);
        }

        // Buscamos el estado del comercio dueño de esta sucursal
        $sucursal = Sucursal::with('comercio')->find($user->branch_id);
        $comercio = $sucursal?->comercio;

        if ($comercio) {
            $hoy = now();
            $vencimiento = $comercio->vencimiento_pago ? \Carbon\Carbon::parse($comercio->vencimiento_pago)->endOfDay() : null;

            // 🔥 REGLA 1: Si el estado manual es "suspendido", cortamos
            // 🔥 REGLA 2: Si la fecha de vencimiento ya pasó (y no es un trial ilimitado), cortamos
            if ($comercio->status === 'suspendido' || ($vencimiento && $hoy->greaterThan($vencimiento))) {
                
                // Si la fecha pasó pero el status seguía "activo", lo cambiamos automáticamente
                if ($comercio->status !== 'suspendido') {
                    $comercio->update(['status' => 'suspendido']);
                }

                // Si es una petición de Inercia/Axios, mandamos redirección limpia
                return redirect()->route('cuenta.suspendida');
            }
        }

        return $next($request);
    }
}