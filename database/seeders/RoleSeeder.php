<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiamos la caché de permisos de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. CREAMOS LOS PERMISOS (Las "llaves" del sistema)
        $permisos = [
            'vender en pos',
            'anular ventas',
            'gestionar productos',
            'gestionar sucursales',
            'gestionar usuarios',
            'ver reportes',
            'gestionar cajas'
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // 2. CREAMOS LOS ROLES Y LE DAMOS SUS "LLAVES"

        // CAJERO: Solo vende
        $rolCajero = Role::firstOrCreate(['name' => 'Cajero']);
        $rolCajero->syncPermissions([
            'vender en pos'
        ]);

        // ENCARGADO: Vende, anula, y maneja productos y cajas
        $rolEncargado = Role::firstOrCreate(['name' => 'Encargado']);
        $rolEncargado->syncPermissions([
            'vender en pos', 
            'anular ventas', 
            'gestionar productos',
            'gestionar cajas'
        ]);

        // SUPERADMIN: Hace todo menos tocar el código o cosas muy de sistema
        $rolSuperAdmin = Role::firstOrCreate(['name' => 'SuperAdmin']);
        $rolSuperAdmin->syncPermissions(Permission::all());

        // Administrador Global: El dios del sistema (Vos o tu socio)
        $rolAdministradorGlobal = Role::firstOrCreate(['name' => 'Administrador Global']);
        // Nota: Spatie recomienda que el Administrador Global no tenga permisos asignados uno por uno, 
        // sino que se lo autorice a todo globalmente (lo haremos después), pero por ahora le damos todo.
        $rolAdministradorGlobal->syncPermissions(Permission::all());
    }
}