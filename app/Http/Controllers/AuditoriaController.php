<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query()->with('causer:id,name,email');

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }
        if ($request->filled('usuario_id')) {
            $query->where('causer_id', $request->usuario_id);
        }
        if ($request->filled('evento')) {
            $query->where('event', $request->evento);
        }
        if ($request->filled('modelo')) {
            $query->where('subject_type', $request->modelo);
        }

        $actividades = $query->latest()->paginate(50)->through(function ($item) {
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
            $diff = [];
            $cambios = $props->get('attributes', []);
            $viejos  = $props->get('old', []);
            $todas = array_unique(array_merge(array_keys($cambios), array_keys($viejos)));
            foreach ($todas as $campo) {
                if (in_array($campo, $excluir)) continue;
                $diff[] = [
                    'campo'   => $campo,
                    'antes'   => array_key_exists($campo, $viejos) ? $viejos[$campo] : null,
                    'despues' => array_key_exists($campo, $cambios) ? $cambios[$campo] : null,
                ];
            }

            return [
                'id'          => $item->id,
                'usuario'     => $item->causer?->name ?? 'Sistema',
                'email'       => $item->causer?->email,
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

        $usuarios = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Auditoria/Index', [
            'actividades' => $actividades,
            'usuarios'    => $usuarios,
            'filtros'     => $request->only(['fecha_desde', 'fecha_hasta', 'usuario_id', 'evento', 'modelo']),
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
