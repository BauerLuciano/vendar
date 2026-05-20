<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('cliente')) {
            return redirect()->route('cliente.inicio')->with('error', 'No tenés permisos para acceder al panel de administración.');
        }

        if ($user->is_active === false) {
            return redirect()->route('pending.approval');
        }

        if (!$user->hasRole(['SuperAdmin', 'Administrador Global', 'Encargado', 'Cajero'])) {
            return redirect('/')->with('error', 'No tenés permisos para acceder al panel de administración.');
        }

        return $next($request);
    }
}
