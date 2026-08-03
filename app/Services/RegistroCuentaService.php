<?php

namespace App\Services;

use App\Models\Comercio;
use App\Models\Configuracion;
use App\Models\Plan;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inicializa una cuenta SaaS completa para un usuario recién registrado:
 * crea el Comercio (tenant), el primer Local con el nombre del negocio,
 * inicia el período de prueba y vincula al usuario como Dueño (SuperAdmin).
 */
class RegistroCuentaService
{
    public function inicializarCuenta(User $user, string $nombreComercio, array $datos = []): Comercio
    {
        return DB::transaction(function () use ($user, $nombreComercio, $datos) {
            $plan = Plan::where('nombre', $user->plan_deseado)
                ->where('activo', true)
                ->first()
                ?? Plan::where('slug', 'basico')->first();

            $trialHabilitado = $plan
                && $plan->trial_activo
                && $plan->trial_dias !== null
                && $plan->trial_dias > 0;

            $comercio = Comercio::create([
                'nombre'                => $nombreComercio,
                'slug'                  => Str::slug($nombreComercio . '-' . Str::random(6)),
                'plan'                  => $plan?->slug ?? 'basico',
                'plan_id'               => $plan?->id,
                'status'                => $trialHabilitado ? 'trial' : 'activo',
                'limite_sucursales'     => $plan?->sucursales_limit ?? 1,
                'limite_usuarios'       => $plan?->usuarios_limit ?? 1,
                'modulos_habilitados'   => $plan?->modulos ?? ['pos' => true],
                'vencimiento_pago'      => $trialHabilitado
                    ? now()->addDays($plan->trial_dias)
                    : null,
            ]);

            Configuracion::updateOrCreate(
                ['comercio_id' => $comercio->id, 'clave' => 'nombre_empresa'],
                ['valor' => $comercio->nombre]
            );

            // Datos de contacto del negocio → config del comercio para reportes y tickets
            foreach (['telefono' => 'telefono', 'direccion' => 'direccion'] as $clave => $grupo) {
                $valor = $datos[$clave] ?? null;
                if ($valor !== null && $valor !== '') {
                    Configuracion::updateOrCreate(
                        ['comercio_id' => $comercio->id, 'clave' => $clave],
                        ['valor' => $valor]
                    );
                }
            }

            // Primer local: siempre lleva el nombre del negocio (nunca "Casa Central").
            // Dirección, teléfono y coordenadas llegan desde el formulario de registro.
            $sucursal = Sucursal::create([
                'comercio_id' => $comercio->id,
                'nombre'      => $nombreComercio,
                'direccion'   => $datos['direccion'] ?? null,
                'telefono'    => $datos['telefono'] ?? null,
                'latitud'     => $datos['latitud'] ?? null,
                'longitud'    => $datos['longitud'] ?? null,
                'estado'      => true,
            ]);

            $user->forceFill([
                'is_active'   => true,
                'comercio_id' => $comercio->id,
                'branch_id'   => $sucursal->id,
            ])->save();

            $user->syncRoles(['SuperAdmin']);

            return $comercio;
        });
    }
}
