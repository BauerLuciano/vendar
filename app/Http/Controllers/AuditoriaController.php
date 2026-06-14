<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;
use App\Models\Consumidor;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query()->with('causer');

        $user = $request->user();

        if ($user && !$user->hasRole('SuperAdmin')) {
            $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;
            if ($comercioId) {
                $userIds = User::where('comercio_id', $comercioId)->pluck('id');
                $query->whereIn('causer_id', $userIds);
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }
        if ($request->filled('usuario_id')) {
            $query->where('causer_type', User::class)->where('causer_id', $request->usuario_id);
        }
        if ($request->filled('consumidor_id')) {
            $query->where('causer_type', Consumidor::class)->where('causer_id', $request->consumidor_id);
        }
        if ($request->filled('evento')) {
            $query->where('event', $request->evento);
        }
        if ($request->filled('modelo')) {
            $query->where('subject_type', $request->modelo);
        }

        $actividades = $query->latest()->paginate(8)->through(function ($item) {
            $props = $item->properties ?? collect();
            $userAgent = $props->get('user_agent', '');

            $descripcion = match ($item->event) {
                'created' => 'Creación de ' . ($item->subject_type ? class_basename($item->subject_type) : 'registro'),
                'updated' => 'Actualización de ' . ($item->subject_type ? class_basename($item->subject_type) : 'registro'),
                'deleted' => 'Eliminación de ' . ($item->subject_type ? class_basename($item->subject_type) : 'registro'),
                'login'   => 'Inicio de sesión',
                'logout'  => 'Cierre de sesión',
                default   => $item->description ?? '—',
            };

            $excluir = ['created_at', 'updated_at', 'deleted_at'];
            $etiquetas = [
                'id'               => 'ID',
                'turno_caja_id'    => 'Turno Caja',
                'consumidor_id'    => 'Cliente',
                'metodo_pago'      => 'Método de Pago',
                'total'            => 'Total',
                'estado'           => 'Estado',
                'motivo_anulacion' => 'Motivo Anulación',
                'pagos'            => 'Pagos',
                'nombre'           => 'Nombre',
                'apellido'         => 'Apellido',
                'email'            => 'Email',
                'telefono'         => 'Teléfono',
                'direccion'        => 'Dirección',
                'precio_compra'    => 'Precio Compra',
                'precio_venta'     => 'Precio Venta',
                'stock_minimo'     => 'Stock Mínimo',
                'codigo_barras'    => 'Código de Barras',
                'sucursal_id'      => 'Sucursal',
                'categoria_id'     => 'Categoría',
                'marca_id'         => 'Marca',
                'proveedor_id'     => 'Proveedor',
                'user_id'          => 'Usuario',
                'caja_id'          => 'Caja',
                'descripcion'      => 'Descripción',
                'fecha'            => 'Fecha',
                'hora_apertura'    => 'Hora Apertura',
                'hora_cierre'      => 'Hora Cierre',
                'monto_apertura'   => 'Monto Apertura',
                'monto_cierre'     => 'Monto Cierre',
                'observaciones'    => 'Observaciones',
                'password'         => 'Contraseña',
            ];
            $diff = [];
            $cambios = $props->get('attributes', []);
            $viejos  = $props->get('old', []);
            $todas = array_unique(array_merge(array_keys($cambios), array_keys($viejos)));
            foreach ($todas as $campo) {
                if (in_array($campo, $excluir)) continue;
                $antes = array_key_exists($campo, $viejos) ? $viejos[$campo] : null;
                $despues = array_key_exists($campo, $cambios) ? $cambios[$campo] : null;
                $diff[] = [
                    'campo'   => $etiquetas[$campo] ?? $campo,
                    'antes'   => $antes,
                    'despues' => $despues,
                ];
            }

            $causer = $item->causer;
            $nombreUsuario = match (true) {
                $causer === null => 'Sistema',
                property_exists($causer, 'nombre') => trim(($causer->nombre ?? '') . ' ' . ($causer->apellido ?? '')),
                default => $causer->name ?? 'Sistema',
            };

            return [
                'id'          => $item->id,
                'usuario'     => $nombreUsuario,
                'email'       => $causer?->email,
                'accion'      => $item->event ?? 'created',
                'descripcion' => $descripcion,
                'modelo'      => $item->subject_type ? class_basename($item->subject_type) : '—',
                'modelo_id'   => $item->subject_id,
                'diff'        => $diff,
                'tiene_diff'  => count($diff) > 0,
                'ip'          => $props->get('ip', '—'),
                'user_agent'  => $userAgent,
                'navegador'   => $this->detectarNavegador($userAgent),
                'fecha'       => $item->created_at->format('d/m/Y H:i'),
            ];
        });

        $usuarios = $user && !$user->hasRole('SuperAdmin')
            ? User::select('id', 'name')
                ->where('comercio_id', $user->comercio_id ?? $user->branch?->comercio_id)
                ->orderBy('name')
                ->get()
            : User::select('id', 'name')->orderBy('name')->get();

        $comercioId = auth()->user()->branch?->comercio_id;
        $consumidores = Consumidor::select('id', 'nombre', 'apellido')
            ->when($comercioId, fn($q) => $q->where('comercio_id', $comercioId))
            ->orderBy('nombre')->get()->map(fn($c) => [
            'id' => $c->id,
            'nombre' => trim(($c->nombre ?? '') . ' ' . ($c->apellido ?? '')),
        ]);

        return Inertia::render('Auditoria/Index', [
            'actividades'  => $actividades,
            'usuarios'     => $usuarios,
            'consumidores' => $consumidores,
            'filtros'      => $request->only(['fecha_desde', 'fecha_hasta', 'usuario_id', 'consumidor_id', 'evento', 'modelo']),
        ]);
    }

    private function detectarNavegador(string $ua): array
    {
        if (empty($ua)) return ['nombre' => '—', 'icono' => 'unknown'];

        $browsers = [
            'Edge'      => '/Edg\/(\S+)/',
            'Opera'     => '/(OPR|Opera)\/(\S+)/',
            'Firefox'   => '/Firefox\/(\S+)/',
            'Safari'    => '/Version\/(\S+).*Safari/',
            'Vivaldi'   => '/Vivaldi\/(\S+)/',
            'Brave'     => '/Brave( Chrome)?\/(\S+)/',
            'Samsung'   => '/SamsungBrowser\/(\S+)/',
            'Chrome'    => '/Chrome\/(\S+)/',
        ];

        foreach ($browsers as $nombre => $regex) {
            if (preg_match($regex, $ua)) {
                return ['nombre' => $nombre, 'icono' => strtolower($nombre)];
            }
        }
        return ['nombre' => 'Otro', 'icono' => 'unknown'];
    }
}
