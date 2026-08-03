<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Configuracion extends Model
{
    use HasFactory, Auditable;

    protected $table = 'configuraciones'; // Le decimos explícitamente cómo se llama la tabla

    protected $fillable = [
        'comercio_id',
        'clave',
        'valor',
        'tipo',
        'grupo',
    ];

    // Devuelve la configuración de un comercio (clave => valor), mezclando los
    // defaults globales (comercio_id NULL) con los overrides propios del comercio.
    // Las filas del comercio tienen prioridad sobre las globales.
    public static function paraComercio(?int $comercioId): array
    {
        $query = self::whereNull('comercio_id');
        if ($comercioId) {
            $query->orWhere('comercio_id', $comercioId);
        }

        $resultado = [];
        foreach ($query->orderByRaw('comercio_id IS NULL DESC')->get() as $row) {
            $resultado[$row->clave] = $row->valor;
        }

        return $resultado;
    }

    // Función ayudante súper útil para traer valores rápido en cualquier lado del sistema
    public static function getValor($clave, $default = null, ?int $comercioId = null)
    {
        return self::paraComercio($comercioId)[$clave] ?? $default;
    }
}