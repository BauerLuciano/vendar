<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE users
            SET comercio_id = (
                SELECT sucursales.comercio_id
                FROM sucursales
                WHERE sucursales.id = users.branch_id
                LIMIT 1
            )
            WHERE comercio_id IS NULL
              AND branch_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::table('users')->whereNotNull('comercio_id')->update(['comercio_id' => null]);
    }
};
