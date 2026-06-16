<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_estado_check');
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_estado_check CHECK (estado::text = ANY (ARRAY['Completada'::text, 'Cancelada'::text, 'Pendiente'::text]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_estado_check');
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_estado_check CHECK (estado::text = ANY (ARRAY['Completada'::text, 'Cancelada'::text]))");
    }
};
