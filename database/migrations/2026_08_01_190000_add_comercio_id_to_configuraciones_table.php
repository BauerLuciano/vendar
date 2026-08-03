<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropUnique('configuraciones_clave_unique');
            $table->unsignedBigInteger('comercio_id')->nullable()->after('id');
            $table->foreign('comercio_id')
                ->references('id')
                ->on('comercios')
                ->onDelete('cascade');
            $table->unique(['comercio_id', 'clave'], 'configuraciones_comercio_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropUnique('configuraciones_comercio_clave_unique');
            $table->dropForeign(['comercio_id']);
            $table->dropColumn('comercio_id');
            $table->unique('clave');
        });
    }
};
