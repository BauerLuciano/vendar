<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Los usuarios existentes ya fueron aprobados antes del alta automática,
        // por lo que se consideran verificados (equivalente funcional).
        DB::table('users')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        DB::table('users')->update(['email_verified_at' => null]);
    }
};
