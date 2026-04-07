<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'luciano@gmail.com'], // Busca por este email
            [
                'name' => 'Luciano', 
                'password' => Hash::make('123456'),
                'branch_id' => 1, // Atado a la Sucursal Central
            ]
        );

        // ¡ACÁ ESTÁ LA MAGIA DE SPATIE!
        // Le damos el poder absoluto. (Asegurate de que el nombre coincida exacto con cómo lo creaste en tu RoleSeeder)
        $user->assignRole('SuperAdmin'); 
    }
}