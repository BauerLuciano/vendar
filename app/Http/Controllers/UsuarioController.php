<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    /**
     * Roles que nunca se pueden asignar desde la gestión de usuarios.
     * `Administrador Global` es de plataforma y `cliente` es de la tienda pública.
     */
    private const ROLES_NO_ASIGNABLES = ['Administrador Global', 'cliente'];

    public function index(Request $request)
    {
        $actor = $request->user();
        $esAdminGlobal = $this->esAdminGlobal($actor);
        $comercioId = $esAdminGlobal ? null : $this->comercioDelActor($actor);

        if (!$esAdminGlobal && !$comercioId) {
            abort(403, 'Tu usuario no tiene un comercio asignado.');
        }

        $search = $request->input('search');
        if (!is_string($search)) {
            $search = null;
        }

        $sucursal_id = $request->input('sucursal_id', 'all');
        $rol = $request->input('rol', 'all');
        if (!is_string($rol)) {
            $rol = 'all';
        }
        $comercioFiltro = $request->input('comercio_id', 'all');

        // Nunca mostramos roles de plataforma ni clientes de la tienda.
        $query = User::with(['branch', 'sucursales', 'roles', 'comercio'])
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', self::ROLES_NO_ASIGNABLES);
            });

        if (!$esAdminGlobal) {
            $this->aplicarScopeComercio($query, $comercioId);
        } elseif ($this->esIdValido($comercioFiltro)) {
            $query->where('comercio_id', (int) $comercioFiltro);
        }

        $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('apellido', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");

                if (is_numeric($search)) {
                    $sub->orWhere('id', $search);
                }
            });
        });

        if ($this->esIdValido($sucursal_id)) {
            $query->where(function ($sub) use ($sucursal_id) {
                $sub->where('branch_id', (int) $sucursal_id)
                    ->orWhereHas('sucursales', fn ($qq) => $qq->where('sucursales.id', (int) $sucursal_id));
            });
        }

        if ($rol !== 'all' && $rol !== null && $rol !== '') {
            $query->whereHas('roles', function ($sub) use ($rol) {
                $sub->where('name', $rol);
            });
        }

        $usuarios = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $roles = Role::whereNotIn('name', self::ROLES_NO_ASIGNABLES)
            ->orderBy('name')
            ->get();

        $sucursales = Sucursal::query()
            ->when(!$esAdminGlobal, fn ($q) => $q->where('comercio_id', $comercioId))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'comercio_id']);

        $comercios = $esAdminGlobal
            ? Comercio::orderBy('nombre')->get(['id', 'nombre'])
            : collect();

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'sucursales' => $sucursales,
            'comercios' => $comercios,
            'esAdminGlobal' => $esAdminGlobal,
            'comercioActualId' => $comercioId,
            'filtros' => $request->only(['search', 'sucursal_id', 'rol', 'comercio_id']),
        ]);
    }

    public function show(Request $request, User $usuario)
    {
        $this->autorizarAccesoAlUsuario($request->user(), $usuario);

        return response()->json(
            $usuario->load(['branch', 'sucursales', 'roles', 'comercio'])
        );
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $esAdminGlobal = $this->esAdminGlobal($actor);

        if (!$esAdminGlobal && !$this->comercioDelActor($actor)) {
            abort(403, 'Tu usuario no tiene un comercio asignado.');
        }

        $comercioDestino = $this->resolverComercioDestino($request, $actor);
        $this->normalizarEntrada($request);

        $validated = $request->validate(
            $this->reglasValidacion($comercioDestino),
            $this->mensajesValidacion()
        );

        $apellido = $validated['apellido'] ?? null;

        $usuario = new User();
        $usuario->name = $this->componerNombre($validated['name'], $apellido);
        $usuario->apellido = $apellido;
        $usuario->telefono = $validated['telefono'] ?? null;
        $usuario->email = mb_strtolower(trim($validated['email']));
        $usuario->password = Hash::make($validated['password']);
        $usuario->branch_id = $validated['branch_id'];
        $usuario->comercio_id = $comercioDestino;
        $usuario->is_active = true;
        $usuario->save();

        $usuario->sucursales()->sync(
            $this->sucursalesValidadas($validated, $comercioDestino)
        );

        $usuario->assignRole($validated['rol']);

        $this->registrarAuditoriaGlobal($actor, $usuario, 'creado', $validated['rol'], $comercioDestino);

        return redirect()->back()->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $actor = $request->user();
        $esAdminGlobal = $this->esAdminGlobal($actor);

        $this->autorizarAccesoAlUsuario($actor, $usuario);

        $comercioDestino = $this->resolverComercioDestino($request, $actor, $usuario);
        $this->normalizarEntrada($request);

        $validated = $request->validate(
            $this->reglasValidacion($comercioDestino, $usuario),
            $this->mensajesValidacion()
        );

        $apellido = $validated['apellido'] ?? null;
        $emailEntrante = mb_strtolower(trim($validated['email']));

        $usuario->name = $this->componerNombre($validated['name'], $apellido);
        $usuario->apellido = $apellido;
        $usuario->telefono = $validated['telefono'] ?? null;

        // Si el email no cambió (ignorando mayúsculas), conservamos el valor
        // original para no romper el login de usuarios históricos.
        if (mb_strtolower(trim((string) $usuario->email)) !== $emailEntrante) {
            $usuario->email = $emailEntrante;
        }

        if (!empty($validated['password'] ?? null)) {
            $usuario->password = Hash::make($validated['password']);
        }

        $usuario->branch_id = $validated['branch_id'];
        $usuario->comercio_id = $comercioDestino;
        $usuario->save();

        $usuario->sucursales()->sync(
            $this->sucursalesValidadas($validated, $comercioDestino)
        );

        $usuario->syncRoles([$validated['rol']]);

        $this->registrarAuditoriaGlobal($actor, $usuario, 'actualizado', $validated['rol'], $comercioDestino);

        return redirect()->back()->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $usuario)
    {
        $actor = $request->user();

        $this->autorizarAccesoAlUsuario($actor, $usuario);

        if ($usuario->id === $actor->id) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar tu propio usuario.']);
        }

        if ($this->esAdminGlobal($actor)) {
            $this->registrarAuditoriaGlobal(
                $actor,
                $usuario,
                'eliminado',
                $usuario->roles->pluck('name')->join(', '),
                (int) ($usuario->comercio_id ?? $usuario->branch?->comercio_id ?? 0)
            );
        }

        $usuario->delete();

        return redirect()->back()->with('exito', 'Usuario eliminado del sistema.');
    }

    private function aplicarScopeComercio(Builder $query, int $comercioId): void
    {
        $query->where(function ($q) use ($comercioId) {
            $q->where('comercio_id', $comercioId)
                // Usuarios históricos sin comercio_id, pero con sucursal del comercio.
                ->orWhere(function ($legacy) use ($comercioId) {
                    $legacy->whereNull('comercio_id')
                        ->whereHas('branch', fn ($branch) => $branch->where('comercio_id', $comercioId));
                });
        });
    }

    private function autorizarAccesoAlUsuario(User $actor, User $usuario): void
    {
        // Los usuarios de plataforma y los clientes de la tienda pública no se
        // administran desde esta pantalla: el formulario no puede representar
        // esos roles y editarlos los degradaría.
        if ($usuario->hasAnyRole(self::ROLES_NO_ASIGNABLES)) {
            abort(403, 'Este usuario no se administra desde la gestión de usuarios.');
        }

        if ($this->esAdminGlobal($actor)) {
            return;
        }

        $comercioId = $this->comercioDelActor($actor);

        if (!$comercioId || !$this->usuarioPerteneceAlComercio($usuario, $comercioId)) {
            abort(403, 'Este usuario no pertenece a tu comercio.');
        }
    }

    private function resolverComercioDestino(Request $request, User $actor, ?User $objetivo = null): int
    {
        if ($this->esAdminGlobal($actor)) {
            if ($request->filled('comercio_id')) {
                $comercio = Comercio::find($request->input('comercio_id'));

                if (!$comercio) {
                    throw ValidationException::withMessages([
                        'comercio_id' => 'Seleccioná un comercio válido.',
                    ]);
                }

                return (int) $comercio->id;
            }

            $comercioActual = $objetivo?->comercio_id ?? $objetivo?->branch?->comercio_id;

            if ($comercioActual) {
                return (int) $comercioActual;
            }

            throw ValidationException::withMessages([
                'comercio_id' => 'Seleccioná el comercio al que pertenece el usuario.',
            ]);
        }

        $comercioId = $this->comercioDelActor($actor);

        if (!$comercioId) {
            abort(403, 'Tu usuario no tiene un comercio asignado.');
        }

        return $comercioId;
    }

    private function reglasValidacion(int $comercioDestino, ?User $usuario = null): array
    {
        $idIgnorado = $usuario?->id;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\p{L}]+(?:[\s\'\-][\p{L}]+)*$/u',
            ],
            'apellido' => [
                'nullable',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\p{L}]+(?:[\s\'\-][\p{L}]+)*$/u',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($idIgnorado) {
                    $email = mb_strtolower(trim((string) $value));

                    $existe = User::whereRaw('lower(email) = ?', [$email])
                        ->when($idIgnorado, fn ($q) => $q->where('id', '!=', $idIgnorado))
                        ->exists();

                    if ($existe) {
                        $fail('Este email ya está registrado.');
                    }
                },
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+\-()\s.]+$/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $digitos = preg_replace('/\D/', '', (string) $value);

                    if (strlen($digitos) < 8 || strlen($digitos) > 15) {
                        $fail('Ingresá un teléfono válido (entre 8 y 15 dígitos).');
                    }
                },
            ],
            'password' => [
                $usuario ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('sucursales', 'id')
                    ->where(fn ($query) => $query->where('comercio_id', $comercioDestino)),
            ],
            'rol' => [
                'required',
                'string',
                Rule::in($this->rolesAsignables()),
            ],
            'sucursales' => ['nullable', 'array'],
            'sucursales.*' => [
                'integer',
                Rule::exists('sucursales', 'id')
                    ->where(fn ($query) => $query->where('comercio_id', $comercioDestino)),
            ],
            'comercio_id' => ['nullable', 'integer', 'exists:comercios,id'],
        ];
    }

    private function mensajesValidacion(): array
    {
        return [
            'name.required' => 'Ingresá un nombre válido.',
            'name.string' => 'Ingresá un nombre válido.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar los 100 caracteres.',
            'name.regex' => 'Ingresá un nombre válido. Solo se permiten letras, espacios, apóstrofes y guiones.',
            'apellido.string' => 'Ingresá un apellido válido.',
            'apellido.min' => 'El apellido debe tener al menos 2 caracteres.',
            'apellido.max' => 'El apellido no puede superar los 100 caracteres.',
            'apellido.regex' => 'El apellido no puede contener números ni símbolos no permitidos.',
            'email.required' => 'Ingresá un email válido.',
            'email.string' => 'Ingresá un email válido.',
            'email.email' => 'Ingresá un email válido.',
            'email.max' => 'El email no puede superar los 255 caracteres.',
            'telefono.string' => 'Ingresá un teléfono válido.',
            'telefono.regex' => 'El teléfono contiene caracteres no permitidos.',
            'telefono.max' => 'El teléfono es demasiado largo.',
            'password.required' => 'Ingresá una contraseña.',
            'password.string' => 'Ingresá una contraseña válida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'branch_id.required' => 'Seleccioná una sucursal base.',
            'branch_id.integer' => 'Seleccioná una sucursal válida.',
            'branch_id.exists' => 'La sucursal seleccionada no pertenece al comercio.',
            'sucursales.array' => 'Las sucursales seleccionadas no son válidas.',
            'sucursales.*.integer' => 'Una de las sucursales seleccionadas no es válida.',
            'sucursales.*.exists' => 'Una de las sucursales seleccionadas no pertenece al comercio.',
            'rol.required' => 'Seleccioná un rol.',
            'rol.string' => 'Seleccioná un rol válido.',
            'rol.in' => 'El rol seleccionado no está permitido.',
            'comercio_id.integer' => 'Seleccioná un comercio válido.',
            'comercio_id.exists' => 'Seleccioná un comercio válido.',
        ];
    }

    private function normalizarEntrada(Request $request): void
    {
        $request->merge([
            'name' => $this->normalizarTexto($request->input('name')),
            'apellido' => $this->normalizarTexto($request->input('apellido')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'telefono' => $this->normalizarTexto($request->input('telefono')),
        ]);
    }

    private function normalizarTexto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = preg_replace('/\s+/u', ' ', trim((string) $valor));

        return $texto === '' ? null : $texto;
    }

    private function componerNombre(string $nombre, ?string $apellido): string
    {
        return $apellido ? $nombre.' '.$apellido : $nombre;
    }

    private function sucursalesValidadas(array $validated, int $comercioDestino): array
    {
        $ids = array_map('intval', $validated['sucursales'] ?? []);
        $ids[] = (int) $validated['branch_id'];
        $ids = array_values(array_unique($ids));

        return Sucursal::where('comercio_id', $comercioDestino)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    private function rolesAsignables(): array
    {
        return Role::whereNotIn('name', self::ROLES_NO_ASIGNABLES)
            ->pluck('name')
            ->all();
    }

    private function esAdminGlobal(User $actor): bool
    {
        return $actor->hasRole('Administrador Global');
    }

    private function comercioDelActor(User $actor): ?int
    {
        if ($actor->comercio_id) {
            return (int) $actor->comercio_id;
        }

        $comercioId = $actor->branch?->comercio_id;

        return $comercioId ? (int) $comercioId : null;
    }

    private function usuarioPerteneceAlComercio(User $usuario, int $comercioId): bool
    {
        if ((int) $usuario->comercio_id === $comercioId) {
            return true;
        }

        if ($usuario->comercio_id === null) {
            return $usuario->branch && (int) $usuario->branch->comercio_id === $comercioId;
        }

        return false;
    }

    private function esIdValido(mixed $valor): bool
    {
        return $valor !== null
            && $valor !== ''
            && $valor !== 'all'
            && is_numeric($valor)
            && (int) $valor > 0;
    }

    private function registrarAuditoriaGlobal(
        User $actor,
        User $usuario,
        string $accion,
        string $rol,
        int $comercioId
    ): void {
        if (!$this->esAdminGlobal($actor)) {
            return;
        }

        activity()
            ->causedBy($actor)
            ->performedOn($usuario)
            ->withProperties([
                'accion' => $accion,
                'rol' => $rol,
                'comercio_id' => $comercioId,
                'usuario_afectado_id' => $usuario->id,
            ])
            ->log('usuario_administrado_por_administrador_global');
    }
}
