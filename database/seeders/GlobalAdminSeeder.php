<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class GlobalAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Nos aseguramos de que el rol exista (firstOrCreate evita duplicados si lo corrés 2 veces)
        $rolAdmin = Role::firstOrCreate(['name' => 'Administrador Global']);

        // 2. Creamos tu usuario maestro (cambiá el email y password por los que quieras usar de verdad)
        $adminGlobal = User::firstOrCreate(
            ['email' => 'adminvendar@gmail.com'], // Busca por este mail para no duplicarlo
            [
                'name' => 'Admin Vendar',
                'password' => Hash::make('admin'), // Poné una contraseña fuerte
                'branch_id' => null, // 🔥 La clave del éxito: Sin sucursal
            ]
        );

        // 3. Le asignamos el poder absoluto
        $adminGlobal->assignRole($rolAdmin);

        $this->command->info('¡Usuario Administrador Global creado con éxito!');
    }
}