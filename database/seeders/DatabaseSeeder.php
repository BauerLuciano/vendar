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
                'is_active' => true,
                'email_verified_at' => now(),
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
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        // A este lo hacemos Cajero para que puedan probar cómo se bloquea el menú
        $testUser->assignRole('Cajero'); 


        // 4. Planes SaaS
        $this->call([
            PlanSeeder::class,
        ]);

        // 5. Corremos el resto de tus Seeders
        // NOTA: Ya no se auto-crean cajas. Las cajas se crean a mano (o guiado por el wizard de onboarding)
        // para que el usuario aprenda el flujo en la sección Cajas.
        $this->call([
            FixDatosMaestrosSeeder::class, // 1ro: Crea la Sucursal y el Consumidor Final
            ConsumidorSeeder::class,       // 2do: Crea el resto de los clientes
            GlobalAdminSeeder::class,      // 3ro: Crea el Admin Global (si no lo creaste antes)
            StoreConfigSeeder::class,      // 4to: Crea store_configs para todos los comercios
        ]);
    }
}