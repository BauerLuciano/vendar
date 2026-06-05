<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consumidores')) {
            // Limpiar duplicados previos (si los hay)
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
        } else {
            Schema::create('consumidores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('comercio_id')->nullable();
                $table->string('nombre');
                $table->string('apellido')->nullable();
                $table->string('documento')->nullable()->unique();
                $table->string('email')->nullable()->unique();
                $table->string('telefono')->nullable();
                $table->string('direccion')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->decimal('limite_cuenta_corriente', 10, 2)->default(0);
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consumidores');
    }
};