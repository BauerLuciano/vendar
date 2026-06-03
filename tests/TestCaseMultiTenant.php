<?php

namespace Tests;

use App\Models\Consumidor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

abstract class TestCaseMultiTenant extends TestCase
{
    use DatabaseTransactions;

    protected User $adminA;
    protected User $adminB;
    protected User $userA;
    protected User $userB;
    protected Consumidor $consumidorA;
    protected Consumidor $consumidorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateAll();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\QATestDataSeeder::class);

        $this->resetSequences();

        $this->adminA = User::where('email', 'admin.a@test.com')->firstOrFail();
        $this->adminB = User::where('email', 'admin.b@test.com')->firstOrFail();
        $this->userA  = User::where('email', 'user.a@test.com')->firstOrFail();
        $this->userB  = User::where('email', 'user.b@test.com')->firstOrFail();
        $this->consumidorA = Consumidor::where('documento', '11111111')->firstOrFail();
        $this->consumidorB = Consumidor::where('documento', '55555555')->firstOrFail();
    }

    private function resetSequences(): void
    {
        $sequences = DB::select("
            SELECT s.relname AS sequence, c.relname AS table_name
            FROM pg_class s
            JOIN pg_depend d ON d.objid = s.oid
            JOIN pg_class c ON c.oid = d.refobjid
            WHERE s.relkind = 'S'
              AND d.deptype = 'a'
              AND c.relname NOT IN ('migrations')
        ");
        foreach ($sequences as $seq) {
            DB::statement("SELECT setval('{$seq->sequence}', COALESCE((SELECT MAX(id) FROM \"{$seq->table_name}\"), 1))");
        }
    }

    private function truncateAll(): void
    {
        DB::statement('SET session_replication_role = replica;');
        $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
        $skip = ['migrations', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'planes'];
        foreach ($tables as $table) {
            if (in_array($table->tablename, $skip)) continue;
            DB::statement('TRUNCATE TABLE "' . $table->tablename . '" CASCADE;');
        }
        DB::statement('SET session_replication_role = origin;');
    }

    protected function actingAsAdminA(): static
    {
        return $this->actingAs($this->adminA);
    }

    protected function actingAsAdminB(): static
    {
        return $this->actingAs($this->adminB);
    }

    protected function actingAsUserA(): static
    {
        return $this->actingAs($this->userA);
    }

    protected function actingAsUserB(): static
    {
        return $this->actingAs($this->userB);
    }
}
