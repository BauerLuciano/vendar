<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comercio extends Model
{
    protected $table = 'comercios';

    // 🔥 LA CLAVE: Le decimos a Laravel exactamente qué campos PUEDE guardar masivamente
    protected $fillable = [
        'nombre',
        'slug',
        'plan',
        'status',
        'limite_sucursales',
        'vencimiento_pago',
        'modulos_habilitados',
    ];

    protected $casts = [
        'modulos_habilitados' => 'array',
        'vencimiento_pago' => 'date',
    ];

    // 🔥 NUEVA RELACIÓN: Un comercio tiene muchas sucursales
    public function sucursales()
    {
        return $this->hasMany(Sucursal::class, 'comercio_id');
    }
}