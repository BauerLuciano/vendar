<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Inertia\Inertia;

class RoleController extends Controller
{
    private const ROLES_PROTEGIDOS = ['Administrador Global', 'SuperAdmin'];

    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permisos = Permission::all();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permisos' => $permisos,
            'esAdminGlobal' => $this->esAdminGlobal(),
            'rolesProtegidos' => self::ROLES_PROTEGIDOS,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['nombre' => $this->normalizarNombre($request->input('nombre'))]);

        $request->validate([
            'nombre' => 'required|string|max:255',
            'permisos' => 'nullable|array'
        ], $this->mensajesValidacion());

        if ($this->esNombreProtegido($request->nombre)) {
            throw ValidationException::withMessages([
                'nombre' => 'Ese nombre está reservado para un rol del sistema.',
            ]);
        }

        if ($this->existeRolConNombre($request->nombre)) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un rol con ese nombre.',
            ]);
        }

        $role = Role::create(['name' => $request->nombre, 'guard_name' => 'web']);

        if ($request->has('permisos')) {
            $role->syncPermissions($request->permisos);
        }

        return redirect()->back()->with('exito', 'Rol creado exitosamente.');
    }

    /**
     * Actualiza un Rol.
     *
     * Los roles de sistema no se pueden renombrar nunca. Los usuarios de
     * comercio tampoco pueden modificar sus permisos; el Administrador Global
     * sí puede administrarlos, pero manteniendo el nombre original.
     */
    public function update(Request $request, Role $role)
    {
        $esProtegido = $this->esNombreProtegido($role->name);
        $esAdminGlobal = $this->esAdminGlobal();

        if ($esProtegido && !$esAdminGlobal) {
            abort(403, 'No podés modificar un rol del sistema.');
        }

        $request->merge(['nombre' => $this->normalizarNombre($request->input('nombre'))]);

        $request->validate([
            'nombre' => 'required|string|max:255',
            'permisos' => 'nullable|array'
        ], $this->mensajesValidacion());

        if ($esProtegido && $request->nombre !== $role->name) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre de un rol del sistema no se puede modificar.',
            ]);
        }

        if (!$esProtegido && $this->esNombreProtegido($request->nombre)) {
            throw ValidationException::withMessages([
                'nombre' => 'Ese nombre está reservado para un rol del sistema.',
            ]);
        }

        if ($this->existeRolConNombre($request->nombre, $role->id)) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un rol con ese nombre.',
            ]);
        }

        if (!$esProtegido) {
            $role->update(['name' => $request->nombre]);
        }

        $role->syncPermissions($request->permisos ?? []);

        return redirect()->back()->with('exito', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role)
    {
        // Los roles base del sistema no se eliminan desde ningún comercio.
        if ($this->esNombreProtegido($role->name)) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar los roles principales del sistema.']);
        }

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

    private function esAdminGlobal(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Administrador Global');
    }

    private function esNombreProtegido(string $nombre): bool
    {
        $normalizado = mb_strtolower($nombre);

        foreach (self::ROLES_PROTEGIDOS as $protegido) {
            if ($normalizado === mb_strtolower($protegido)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarNombre(mixed $nombre): string
    {
        if (!is_string($nombre)) {
            throw ValidationException::withMessages([
                'nombre' => 'Ingresá un nombre válido para el rol.',
            ]);
        }

        return trim($nombre);
    }

    private function mensajesValidacion(): array
    {
        return [
            'nombre.required' => 'Ingresá un nombre para el rol.',
            'nombre.string' => 'Ingresá un nombre válido para el rol.',
            'nombre.max' => 'El nombre del rol no puede superar los 255 caracteres.',
            'permisos.array' => 'Los permisos seleccionados no son válidos.',
        ];
    }

    private function existeRolConNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return Role::whereRaw('lower(name) = ?', [mb_strtolower($nombre)])
            ->when($exceptoId, fn ($query) => $query->where('id', '!=', $exceptoId))
            ->exists();
    }
}
