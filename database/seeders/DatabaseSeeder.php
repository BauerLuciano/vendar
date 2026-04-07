<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. PRIMERO CREAMOS LOS ROLES Y PERMISOS 
        // (Asegurate de tener este seeder creado, o el que uses para cargar roles)
        $this->call([
            RoleSeeder::class, // <- ESTO TIENE QUE IR ANTES DE CREAR LOS USUARIOS
        ]);

        // 2. Crear TU usuario y darle poder absoluto
        $luciano = User::updateOrCreate(
            ['email' => 'luciano@gmail.com'],
            [
                'name' => 'Luciano',
                'password' => Hash::make('123456'), 
            ]
        );
        // ¡Le asignamos el rol!
        $luciano->assignRole('SuperAdmin'); 


        // 3. Crear el usuario de prueba y darle rol de Cajero
        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'), 
            ]
        );
        // A este lo hacemos Cajero para que puedan probar cómo se bloquea el menú
        $testUser->assignRole('Cajero'); 


        // 4. Corremos el resto de tus Seeders
        $this->call([
            FixDatosMaestrosSeeder::class, // 1ro: Crea la Sucursal y el Consumidor Final
            CajaSeeder::class,             // 2do: Crea las Cajas (ahora sí encuentra la sucursal)
            ConsumidorSeeder::class,       // 3ro: Crea el resto de los clientes
        ]);
    }
}