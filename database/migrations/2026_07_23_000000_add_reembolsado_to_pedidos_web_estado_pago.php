<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pedidos_web 
            DROP CONSTRAINT pedidos_web_estado_pago_check,
            ADD CONSTRAINT pedidos_web_estado_pago_check 
            CHECK (estado_pago IN ('pendiente', 'pagado', 'rechazado', 'reembolsado'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pedidos_web 
            DROP CONSTRAINT pedidos_web_estado_pago_check,
            ADD CONSTRAINT pedidos_web_estado_pago_check 
            CHECK (estado_pago IN ('pendiente', 'pagado', 'rechazado'))
        ");
    }
};
