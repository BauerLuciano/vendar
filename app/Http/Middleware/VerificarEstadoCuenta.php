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
        // 1. Dejamos pasar las rutas de autenticación, logout, landing y la vista de suspensión
        if ($request->path() === '/' || $request->routeIs('login', 'register', 'logout', 'cuenta.suspendida', 'pending.approval', 'elegir.sucursal', 'elegir.sucursal.store', 'admin.comercios.*', 'impersonate.*', 'mercadopago.notificacion', 'viumi.webhook', 'tienda.*', 'api.tienda.*', 'cliente.*', 'suscripcion.*')) {
            return $next($request);
        }

        $user = $request->user();

        // Usuarios no autenticados pasan: el middleware 'auth' de cada ruta
        // protegida es el que se encarga de redirigir al login.
        if (!$user) {
            return $next($request);
        }

        $sucursalId = session('sucursal_activa_id', $user->branch_id);

        // Si no está logueado o es el Admin Global maestro, pasa libre
        if (!$sucursalId || (method_exists($user, 'hasRole') && $user->hasRole('Administrador Global'))) {
            return $next($request);
        }

        // Buscamos el estado del comercio dueño de esta sucursal
        $sucursal = Sucursal::with('comercio.plan')->find($sucursalId);
        $comercio = $sucursal?->comercio;

        if ($comercio) {
            $hoy = now();
            $vencimiento = $comercio->vencimiento_pago ? \Carbon\Carbon::parse($comercio->vencimiento_pago)->endOfDay() : null;

            // Período de mora: vencimiento + días de gracia definidos por el plan.
            // Si el plan no define mora (null), se suspende apenas vence.
            $diasMora = $comercio->plan()?->first()?->dias_mora;
            $moraHasta = $vencimiento && $diasMora !== null ? $vencimiento->copy()->addDays($diasMora) : $vencimiento;

            // 🔥 REGLA 1: Si el estado manual es "suspendido", cortamos
            // 🔥 REGLA 2: Si pasó el vencimiento + días de mora, cortamos
            if ($comercio->status === 'suspendido' || ($moraHasta && $hoy->greaterThan($moraHasta))) {
                
                // Si la fecha pasó pero el status seguía "activo", lo cambiamos automáticamente
                if ($comercio->status !== 'suspendido') {
                    $comercio->status = 'suspendido';
                    $comercio->save();
                }

                // Si es una petición de Inercia/Axios, mandamos redirección limpia
                return redirect()->route('cuenta.suspendida');
            }
        }

        return $next($request);
    }
}