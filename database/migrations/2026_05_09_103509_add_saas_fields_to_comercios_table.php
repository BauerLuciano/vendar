<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            // Pregunta si NO existe 'plan', recién ahí lo crea
            if (!Schema::hasColumn('comercios', 'plan')) {
                $table->string('plan')->default('basico'); 
            }
            
            if (!Schema::hasColumn('comercios', 'estado_cuenta')) {
                $table->string('estado_cuenta')->default('activo');
            }
            
            if (!Schema::hasColumn('comercios', 'modulos')) {
                $table->json('modulos')->nullable();
            }
            
            if (!Schema::hasColumn('comercios', 'vence_at')) {
                $table->timestamp('vence_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            if (Schema::hasColumn('comercios', 'plan')) {
                $table->dropColumn('plan');
            }
            if (Schema::hasColumn('comercios', 'estado_cuenta')) {
                $table->dropColumn('estado_cuenta');
            }
            if (Schema::hasColumn('comercios', 'modulos')) {
                $table->dropColumn('modulos');
            }
            if (Schema::hasColumn('comercios', 'vence_at')) {
                $table->dropColumn('vence_at');
            }
        });
    }
};