<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar datos existentes: aprobada -> recibida (operación completada)
        DB::table('transferencia_sugeridas')
            ->where('estado', 'aprobada')
            ->update(['estado' => 'recibida']);

        // PostgreSQL: dropeamos el CHECK constraint viejo y creamos uno nuevo
        DB::statement('ALTER TABLE transferencia_sugeridas DROP CONSTRAINT IF EXISTS transferencia_sugeridas_estado_check');

        DB::statement("
            ALTER TABLE transferencia_sugeridas ADD CONSTRAINT transferencia_sugeridas_estado_check
            CHECK (estado::text = ANY (ARRAY[
                'pendiente'::text,
                'en_transito'::text,
                'recibida'::text,
                'rechazada'::text,
                'cancelada'::text
            ]))
        ");
    }

    public function down(): void
    {
        // Revertir: recibida -> aprobada
        DB::table('transferencia_sugeridas')
            ->where('estado', 'recibida')
            ->update(['estado' => 'aprobada']);

        DB::statement('ALTER TABLE transferencia_sugeridas DROP CONSTRAINT IF EXISTS transferencia_sugeridas_estado_check');

        DB::statement("
            ALTER TABLE transferencia_sugeridas ADD CONSTRAINT transferencia_sugeridas_estado_check
            CHECK (estado::text = ANY (ARRAY[
                'pendiente'::text,
                'aprobada'::text,
                'rechazada'::text
            ]))
        ");
    }
};
