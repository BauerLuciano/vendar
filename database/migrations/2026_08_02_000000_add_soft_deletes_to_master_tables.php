<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'roles', 'recargos_tarjetas', 'payment_method_configurations', 'promotions', 'planes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::table('payment_method_configurations', function (Blueprint $table) {
            $table->dropUnique('pmc_comercio_metodo_provider_unique');
        });
        Schema::table('recargos_tarjetas', function (Blueprint $table) {
            $table->dropUnique(['comercio_id', 'banco', 'tipo_tarjeta', 'cuotas']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('CREATE UNIQUE INDEX pmc_comercio_metodo_provider_unique ON payment_method_configurations (comercio_id, metodo_pago, provider) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX recargos_tarjetas_comercio_banco_tipo_cuotas_unique ON recargos_tarjetas (comercio_id, banco, tipo_tarjeta, cuotas) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX roles_name_guard_name_unique ON roles (name, guard_name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('payment_method_configurations', function (Blueprint $table) {
            $table->dropUnique('pmc_comercio_metodo_provider_unique');
        });
        Schema::table('recargos_tarjetas', function (Blueprint $table) {
            $table->dropUnique('recargos_tarjetas_comercio_banco_tipo_cuotas_unique');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('payment_method_configurations', function (Blueprint $table) {
            $table->unique(['comercio_id', 'metodo_pago', 'provider'], 'pmc_comercio_metodo_provider_unique');
        });
        Schema::table('recargos_tarjetas', function (Blueprint $table) {
            $table->unique(['comercio_id', 'banco', 'tipo_tarjeta', 'cuotas']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'guard_name']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });

        foreach (['users', 'roles', 'recargos_tarjetas', 'payment_method_configurations', 'promotions', 'planes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
