<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedTestData extends Command
{
    protected $signature = 'test:data {--fresh : Truncar datos de prueba antes de seedear}';

    protected $description = 'Puebla la base de datos con datos de prueba multi-tenant para QA';

    public function handle()
    {
        if ($this->option('fresh')) {
            $this->info('Limpiando datos de prueba...');
            $this->truncateTestTables();
        }

        $this->info('Sembrando seeders base...');
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\PlanSeeder']);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\QATestDataSeeder']);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\GlobalAdminSeeder']);

        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   DATOS DE TESTING MULTI-TENANT CREADOS     ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');
        $this->info('── ADMINISTRADOR GLOBAL ──');
        $this->info('  Email: adminvendar@gmail.com / admin');
        $this->info('');
        $this->info('── COMERCIO A (Almacén Norte) ──');
        $this->info('  Admin: admin.a@test.com / password');
        $this->info('  Cajero: user.a@test.com / password');
        $this->info('');
        $this->info('── COMERCIO B (Almacén Sur) ──');
        $this->info('  Admin: admin.b@test.com / password');
        $this->info('  Cajero: user.b@test.com / password');
        $this->info('');
        $this->info('IDs clave: Comercio A=1, B=2 | Sucursales: 1-2 (A), 3-4 (B)');
        $this->info('Cajas: 1-2 (A), 3-5 (B) | Turnos: 1-2 (A), 3-4 (B)');
        $this->info('Productos: 1-10 (A), 11-20 (B) | Consumidores: 1-5 (A), 6-10 (B)');
        $this->info('');
        $this->info('Documentación: docs/TEST_DATA.md');
    }

    private function truncateTestTables(): void
    {
        $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
        $skip = ['migrations', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'planes'];
        $toTruncate = [];
        foreach ($tables as $table) {
            if (!in_array($table->tablename, $skip)) {
                $toTruncate[] = '"' . $table->tablename . '"';
            }
        }
        if (!empty($toTruncate)) {
            DB::statement('SET session_replication_role = replica;');
            DB::statement('TRUNCATE TABLE ' . implode(', ', $toTruncate) . ' RESTART IDENTITY CASCADE;');
            DB::statement('SET session_replication_role = origin;');
        }
    }
}
