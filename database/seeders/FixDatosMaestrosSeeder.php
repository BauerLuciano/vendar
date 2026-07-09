<?php

namespace Database\Seeders;

use App\Models\Sucursal; // <--- Cambiado
use App\Models\Consumidor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixDatosMaestrosSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear al menos una sucursal (ID: 1)
        $sucursal = Sucursal::firstOrCreate(
            ['id' => 1],
            [
                'nombre' => 'Casa Central', 
                'direccion' => 'Dirección de Prueba 123',
                'telefono' => '123456789',
                'tipo' => 'punto_de_venta',
                'estado' => true
            ] 
        );

        // 2. Asignar la sucursal al usuario de prueba (Cambiá branch_id por sucursal_id en la tabla users después!)
        $user = User::find(1);
        if ($user) {
            $user->update(['branch_id' => $sucursal->id]); // Sugerencia: Renombrar a sucursal_id en DB
        }

        // 3. Crear el "Consumidor Final" (ID: 1)
        Consumidor::firstOrCreate(
            ['id' => 1],
            [
                'nombre' => 'Consumidor',
                'apellido' => 'Final',
                'documento' => '00000000',
                'limite_cuenta_corriente' => 0,
                'estado' => true,
            ]
        );

        // Sincronizar secuencias
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('sucursales', 'id'), coalesce(max(id),0) + 1, false) FROM sucursales;");
            DB::statement("SELECT setval(pg_get_serial_sequence('consumidores', 'id'), coalesce(max(id),0) + 1, false) FROM consumidores;");
            DB::statement("SELECT setval(pg_get_serial_sequence('turno_cajas', 'id'), coalesce(max(id),0) + 1, false) FROM turno_cajas;");
        }
    }
}