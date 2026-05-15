<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            // Envíos
            $table->decimal('envio_precio_base', 10, 2)->default(0)->after('status');
            $table->decimal('envio_precio_km', 10, 2)->default(0)->after('envio_precio_base');
            $table->integer('envio_radio_km')->default(5)->after('envio_precio_km');
            
            // Transferencias
            $table->string('transferencia_cbu', 50)->nullable()->after('envio_radio_km');
            $table->string('transferencia_alias', 50)->nullable()->after('transferencia_cbu');
            $table->string('transferencia_titular', 100)->nullable()->after('transferencia_alias');
            
            // Pasarelas automáticas (Preparando el terreno)
            $table->string('mp_access_token')->nullable()->after('transferencia_titular');
            $table->string('payway_public_key')->nullable()->after('mp_access_token');
            $table->boolean('acepta_efectivo')->default(true)->after('payway_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            $table->dropColumn([
                'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
                'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
                'mp_access_token', 'payway_public_key', 'acepta_efectivo'
            ]);
        });
    }
};