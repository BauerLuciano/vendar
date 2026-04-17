<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Schema;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'branch_id' => $request->user()->branch_id,
                    // MANDAMOS LOS ROLES (ej: ["Cajero"])
                    'roles' => $request->user()->getRoleNames(),
                    // MANDAMOS LOS PERMISOS (ej: ["crear ventas", "editar productos"])
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
            ],

            // 🔥 NUEVO: CONFIGURACIONES GLOBALES DE LA EMPRESA
            // Lo envolvemos en fn() para que sea de carga diferida y validamos que la tabla exista
            'empresa' => fn () => Schema::hasTable('configuraciones') 
                            ? Configuracion::pluck('valor', 'clave')->toArray() 
                            : [],

            // Mensajes de sesión (flash)
            'flash' => [
                'exito' => fn () => $request->session()->get('exito'),
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'), // Lo agrego por si usás with('success') en algún lado
            ],
        ];
    }
}