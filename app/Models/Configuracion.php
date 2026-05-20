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
        'clave',
        'valor',
        'tipo',
        'grupo',
    ];

    // Función ayudante súper útil para traer valores rápido en cualquier lado del sistema
    public static function getValor($clave, $default = null)
    {
        $config = self::where('clave', $clave)->first();
        return $config ? $config->valor : $default;
    }
}