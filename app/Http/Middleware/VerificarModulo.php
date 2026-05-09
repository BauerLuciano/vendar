<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Sucursal;
use Symfony\Component\HttpFoundation\Response;

class VerificarModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $user = $request->user();

        if (!$user || !$user->branch_id) {
            return $next($request);
        }

        // Buscamos a qué comercio pertenece el usuario actual
        $sucursal = Sucursal::with('comercio')->find($user->branch_id);
        $comercio = $sucursal?->comercio;

        if (!$comercio) {
            return redirect()->route('dashboard')->with('error', 'Error: Sucursal sin comercio asignado.');
        }

        $modulosHabilitados = $comercio->modulos_habilitados ?? [];

        // 🔥 REGLA DE ORO: Si el módulo no existe o está en false, lo rebotamos
        if (empty($modulosHabilitados[$modulo])) {
            return redirect()->route('dashboard')->with('error', "🔒 Función Premium. El módulo '{$modulo}' no está incluido en tu plan actual. ¡Comunicate con Ventas para activarlo!");
        }

        return $next($request);
    }
}