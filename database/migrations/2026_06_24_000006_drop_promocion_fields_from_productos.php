<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['promocion_activa']);
            $table->dropColumn([
                'precio_promocion',
                'promocion_activa',
                'etiqueta_promocion',
                'promocion_tipo',
                'promocion_fin',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_promocion', 12, 2)->nullable()->after('precio_venta');
            $table->boolean('promocion_activa')->default(false)->after('precio_promocion');
            $table->string('etiqueta_promocion', 50)->nullable()->after('promocion_activa');
            $table->string('promocion_tipo', 20)->nullable()->after('etiqueta_promocion')
                ->comment('manual: creada por admin, vencimiento: automática por fecha de vencimiento');
            $table->date('promocion_fin')->nullable()->after('promocion_tipo');
            $table->index('promocion_activa');
        });
    }
};
