<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique(); // Ej: 'nombre_empresa'
            $table->text('valor')->nullable(); // Ej: 'Mi Kiosco'
            $table->string('tipo')->default('texto'); // Para saber si es texto, numero, imagen, boolean
            $table->string('grupo')->default('general'); // Para separar en pestañas (general, pos, sistema)
            $table->timestamps();
        });

        // 🔥 MAGIA: Insertamos las configuraciones por defecto de una
        DB::table('configuraciones')->insert([
            // PESTAÑA: GENERAL
            ['clave' => 'nombre_empresa', 'valor' => 'Mi Negocio', 'tipo' => 'texto', 'grupo' => 'general'],
            ['clave' => 'cuit', 'valor' => null, 'tipo' => 'texto', 'grupo' => 'general'],
            ['clave' => 'telefono', 'valor' => null, 'tipo' => 'texto', 'grupo' => 'general'],
            ['clave' => 'direccion', 'valor' => null, 'tipo' => 'texto', 'grupo' => 'general'],
            ['clave' => 'logo_empresa', 'valor' => null, 'tipo' => 'imagen', 'grupo' => 'general'],
            
            // PESTAÑA: PUNTO DE VENTA (POS)
            ['clave' => 'ticket_mensaje_pie', 'valor' => '¡Gracias por su compra!', 'tipo' => 'texto', 'grupo' => 'pos'],
            ['clave' => 'formato_impresion', 'valor' => '80mm', 'tipo' => 'select', 'grupo' => 'pos'],
            ['clave' => 'permitir_stock_negativo', 'valor' => '0', 'tipo' => 'boolean', 'grupo' => 'pos'],
            
            // PESTAÑA: SISTEMA
            ['clave' => 'limite_fiado_defecto', 'valor' => '10000', 'tipo' => 'numero', 'grupo' => 'sistema'],
            ['clave' => 'moneda_defecto', 'valor' => 'ARS', 'tipo' => 'texto', 'grupo' => 'sistema'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};