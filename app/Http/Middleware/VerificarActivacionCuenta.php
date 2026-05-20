<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarActivacionCuenta
{
    public function handle(Request $request, Closure $next): Response
    {
        $rutasPermitidas = [
            'login', 'logout', 'pending.approval',
            'cuenta.suspendida', 'register', 'password.*',
            'tienda.publica', 'auth.google', 'auth.google.callback',
            'cliente.inicio',
        ];

        foreach ($rutasPermitidas as $ruta) {
            if ($request->routeIs($ruta)) {
                return $next($request);
            }
        }

        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('Administrador Global')) {
            return $next($request);
        }

        if ($user->hasRole('cliente')) {
            return redirect()->route('cliente.inicio')->with('error', 'No tenés permisos para acceder al panel de administración.');
        }

        if ($user->is_active === false) {
            return redirect()->route('pending.approval');
        }

        return $next($request);
    }
}
