<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Sucursal;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sucursal_id = $request->input('sucursal_id', 'all');
        $rol = $request->input('rol', 'all');
        $estado = $request->input('estado', 'todos');

        // 🔥 FILTRO DE INVISIBILIDAD: Nunca mostramos al Administrador Global ni a los clientes
        // withTrashed() para que los usuarios dados de baja (soft delete) sigan apareciendo como "Inactivo"
        $query = User::withTrashed()
            ->with(['branch', 'sucursales', 'roles'])
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['Administrador Global', 'cliente']);
            });

        // 🔥 MULTI-TENANT: un usuario de comercio solo ve gente de SU comercio.
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId) {
            $sucursalesDelComercio = Sucursal::where('comercio_id', $comercioId)->pluck('id');
            $query->where(function ($q) use ($comercioId, $sucursalesDelComercio) {
                $q->where('comercio_id', $comercioId)
                    ->orWhereIn('branch_id', $sucursalesDelComercio)
                    ->orWhereHas('sucursales', fn ($qq) => $qq->whereIn('sucursal_id', $sucursalesDelComercio));
            });
        }

        $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
                if (is_numeric($search)) {
                    $sub->orWhere('id', $search);
                }
            });
        });

        $query->when($sucursal_id !== 'all', function ($q) use ($sucursal_id) {
            $q->where(function ($sub) use ($sucursal_id) {
                $sub->where('branch_id', $sucursal_id)
                    ->orWhereHas('sucursales', fn($qq) => $qq->where('sucursal_id', $sucursal_id));
            });
        });

        $query->when($rol !== 'all', function ($q) use ($rol) {
            $q->whereHas('roles', function ($sub) use ($rol) {
                $sub->where('name', $rol);
            });
        });

        $query->when($estado === 'inactivos', fn ($q) => $q->onlyTrashed());
        $query->when($estado === 'activos', fn ($q) => $q->whereNull('deleted_at'));

        $usuarios = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        
        // 🔥 Tampoco mostramos el rol de Administrador Global en el selector
        $roles = Role::whereNotIn('name', ['cliente', 'Administrador Global'])->get();

        $sucursales = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->get()
            : Sucursal::all();

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'sucursales' => $sucursales,
            'filtros' => $request->only(['search', 'sucursal_id', 'rol', 'estado'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'password'    => 'required|string|min:8',
            'branch_id'   => $request->rol === 'Administrador Global' ? 'nullable' : 'required|exists:sucursales,id',
            'rol'         => 'required|string|exists:roles,name',
            'sucursales'  => 'nullable|array',
            'sucursales.*' => 'exists:sucursales,id',
        ]);

        $userAuth = auth()->user();
        $comercioId = null;
        if ($request->branch_id) {
            $sucursal = \App\Models\Sucursal::where('comercio_id', $userAuth->branch?->comercio_id)
                ->find($request->branch_id);
            if (!$sucursal) {
                return redirect()->back()->withErrors(['branch_id' => 'La sucursal seleccionada no pertenece a tu comercio.']);
            }
            $comercioId = $sucursal->comercio_id;
        } else {
            $comercioId = $userAuth->branch?->comercio_id;
        }

        $usuario = new User();
        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->password = Hash::make($request->password);
        $usuario->branch_id = $request->branch_id;
        $usuario->comercio_id = $comercioId;
        $usuario->is_active = true;
        // Empleados creados por el dueño: el email ya está validado por quien lo crea.
        $usuario->email_verified_at = now();
        $usuario->save();

        if ($request->filled('sucursales')) {
            $sucursalesValidas = \App\Models\Sucursal::where('comercio_id', $comercioId)
                ->whereIn('id', $request->sucursales)
                ->pluck('id')
                ->toArray();
            $usuario->sucursales()->sync($sucursalesValidas);
        } elseif ($request->branch_id) {
            $usuario->sucursales()->sync([$request->branch_id]);
        }

        $usuario->assignRole($request->rol);

        return redirect()->back()->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $usuario->comercio_id !== $comercioId) {
            abort(403, 'Este usuario no pertenece a tu comercio.');
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'password'    => 'nullable|string|min:8',
            'branch_id'   => $request->rol === 'Administrador Global' ? 'nullable' : 'required|exists:sucursales,id',
            'rol'         => 'required|string|exists:roles,name',
            'sucursales'  => 'nullable|array',
            'sucursales.*' => 'exists:sucursales,id',
        ]);

        // 🔥 MULTI-TENANT: la sucursal asignada debe pertenecer a MI comercio.
        if ($request->branch_id) {
            $sucursal = \App\Models\Sucursal::where('comercio_id', $comercioId)
                ->find($request->branch_id);
            if (!$sucursal) {
                return redirect()->back()->withErrors(['branch_id' => 'La sucursal seleccionada no pertenece a tu comercio.']);
            }
        }

        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->branch_id = $request->branch_id;

        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }

        $usuario->save();

        $comercioId = auth()->user()->branch?->comercio_id;
        if ($request->filled('sucursales')) {
            $sucursalesValidas = \App\Models\Sucursal::where('comercio_id', $comercioId)
                ->whereIn('id', $request->sucursales)
                ->pluck('id')
                ->toArray();
            $usuario->sucursales()->sync($sucursalesValidas);
        } elseif ($request->branch_id) {
            $usuario->sucursales()->sync([$request->branch_id]);
        }

        $usuario->syncRoles([$request->rol]);

        return redirect()->back()->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $usuario->comercio_id !== $comercioId) {
            abort(403, 'Este usuario no pertenece a tu comercio.');
        }

        if ($usuario->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar tu propio usuario.']);
        }

        $usuario->delete();
        return redirect()->back()->with('exito', 'Usuario dado de baja. Su historial se conserva.');
    }

    public function restore($id)
    {
        $usuario = User::withTrashed()->findOrFail($id);

        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $usuario->comercio_id !== $comercioId) {
            abort(403, 'Este usuario no pertenece a tu comercio.');
        }

        if (is_null($usuario->deleted_at)) {
            return redirect()->back()->with('exito', 'El usuario ya estaba activo.');
        }

        $usuario->restore();
        return redirect()->back()->with('exito', 'Usuario reactivado. Ya puede volver a iniciar sesión.');
    }
}