<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Limpiar duplicados previos (si los hay)
        // Para cada documento no nulo repetido, dejamos el registro más antiguo
        // y ponemos documento = null en los demás
        $duplicates = DB::table('consumidores')
            ->select('documento', DB::raw('MIN(id) as min_id'))
            ->whereNotNull('documento')
            ->groupBy('documento')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('consumidores')
                ->where('documento', $dup->documento)
                ->where('id', '!=', $dup->min_id)
                ->update(['documento' => null]);
        }

        $indexExists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'consumidores'"))
            ->pluck('indexname')
            ->contains('consumidores_documento_unique');

        if (!$indexExists) {
            Schema::table('consumidores', function (Blueprint $table) {
                $table->unique('documento');
            });
        }
    }

    public function down(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            $table->dropUnique(['documento']);
        });
    }
};