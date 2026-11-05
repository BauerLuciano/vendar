<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificación defensiva: nunca crear el índice si ya hay duplicados.
        $duplicados = DB::select(<<<'SQL'
            SELECT
                sucursal_id,
                lower(btrim(nombre)) AS nombre_normalizado,
                count(*) AS cantidad,
                string_agg(id::text, ', ' ORDER BY id) AS ids
            FROM cajas
            WHERE deleted_at IS NULL
            GROUP BY sucursal_id, lower(btrim(nombre))
            HAVING count(*) > 1
            ORDER BY sucursal_id, nombre_normalizado
        SQL);

        if (!empty($duplicados)) {
            $detalle = collect($duplicados)
                ->map(fn ($d) => "sucursal {$d->sucursal_id}, nombre '{$d->nombre_normalizado}', IDs: {$d->ids}")
                ->implode('; ');

            throw new RuntimeException(
                "No se puede crear el índice único de cajas: existen duplicados activos. {$detalle}"
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX cajas_sucursal_nombre_unique
             ON cajas (sucursal_id, lower(btrim(nombre)))
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cajas_sucursal_nombre_unique');
    }
};
