<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Crear planes por defecto
        $planes = [
            [
                'id' => 1,
                'nombre' => 'Plan Básico',
                'slug' => 'basico',
                'descripcion' => 'Para emprendedores que inician.',
                'precio_mensual' => 8000,
                'modulos' => json_encode(['pos' => true, 'proveedores' => true, 'lotes' => false, 'fiados' => false, 'auditoria' => false, 'transferencias' => false, 'pedidos_web' => false, 'promociones' => false]),
                'sucursales_limit' => 1,
                'usuarios_limit' => 1,
                'destacado' => false,
                'orden' => 1,
                'trial_dias' => 7,
                'trial_activo' => true,
                'activo' => true,
            ],
            [
                'id' => 2,
                'nombre' => 'Plan Profesional',
                'slug' => 'pro',
                'descripcion' => 'Para comercios en crecimiento.',
                'precio_mensual' => 15000,
                'modulos' => json_encode(['pos' => true, 'proveedores' => true, 'lotes' => true, 'fiados' => false, 'auditoria' => false, 'transferencias' => false, 'pedidos_web' => true, 'promociones' => true]),
                'sucursales_limit' => 3,
                'usuarios_limit' => 3,
                'destacado' => true,
                'orden' => 2,
                'trial_dias' => 14,
                'trial_activo' => true,
                'activo' => true,
            ],
            [
                'id' => 3,
                'nombre' => 'Plan Premium',
                'slug' => 'premium',
                'descripcion' => 'Para empresas con operaciones completas.',
                'precio_mensual' => 35000,
                'modulos' => json_encode(['pos' => true, 'proveedores' => true, 'lotes' => true, 'fiados' => true, 'auditoria' => true, 'transferencias' => true, 'pedidos_web' => true, 'promociones' => true]),
                'sucursales_limit' => 10,
                'usuarios_limit' => 10,
                'destacado' => false,
                'orden' => 3,
                'trial_dias' => 30,
                'trial_activo' => true,
                'activo' => true,
            ],
        ];

        foreach ($planes as $plan) {
            DB::table('planes')->updateOrInsert(
            ['slug' => $plan['slug']],
            $plan
            );       
        }

        // Backfill: mapear comercios existentes con plan string a plan_id
        $map = [
            'basico' => 1,
            'pro' => 2,
            'premium' => 3,
        ];

        foreach ($map as $slug => $planId) {
            DB::table('comercios')
                ->where('plan', $slug)
                ->whereNull('plan_id')
                ->update(['plan_id' => $planId]);
        }
    }
}
