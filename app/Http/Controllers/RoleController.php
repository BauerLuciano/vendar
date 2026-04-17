<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permisos = Permission::all();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permisos' => $permisos
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|unique:roles,name|max:255',
            'permisos' => 'nullable|array'
        ]);

        $role = Role::create(['name' => $request->nombre, 'guard_name' => 'web']);
        
        if ($request->has('permisos')) {
            $role->syncPermissions($request->permisos);
        }

        return redirect()->back()->with('exito', 'Rol creado exitosamente.');
    }

    /**
     * Actualiza un Rol
     * Cambiamos $rol por $role para que coincida con el Resource
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permisos' => 'nullable|array'
        ]);

        $role->update(['name' => $request->nombre]);
        $role->syncPermissions($request->permisos ?? []);

        return redirect()->back()->with('exito', 'Rol actualizado correctamente.');
    }

    /**
     * Elimina un Rol
     * Cambiamos $rol por $role
     */
    public function destroy(Role $role)
    {
        // Seguridad: No borrar roles críticos
        if (in_array($role->name, ['Administrador Global', 'SuperAdmin'])) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar los roles principales del sistema.']);
        }

        // Paso extra: Desvincular todos los permisos antes de borrar (buena práctica)
        $role->syncPermissions([]);
        
        $role->delete();
        
        return redirect()->back()->with('exito', 'Rol eliminado correctamente.');
    }

    public function storePermiso(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|unique:permissions,name|max:255',
            'descripcion' => 'nullable|string|max:255'
        ]);

        Permission::create([
            'name' => $request->nombre,
            'description' => $request->descripcion,
            'guard_name' => 'web'
        ]);

        return redirect()->back()->with('exito', 'Permiso creado exitosamente.');
    }

    public function updatePermiso(Request $request, Permission $permiso)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:permissions,name,' . $permiso->id,
            'descripcion' => 'nullable|string|max:255'
        ]);

        $permiso->update([
            'name' => $request->nombre,
            'description' => $request->descripcion
        ]);

        return redirect()->back()->with('exito', 'Permiso actualizado correctamente.');
    }
}