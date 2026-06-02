<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio_mensual',
        'modulos',
        'sucursales_limit',
        'usuarios_limit',
        'destacado',
        'orden',
        'activo',
    ];

    protected $casts = [
        'modulos' => 'array',
        'precio_mensual' => 'decimal:2',
        'destacado' => 'boolean',
        'activo' => 'boolean',
    ];

    public function comercios()
    {
        return $this->hasMany(Comercio::class, 'plan_id');
    }

    public function comerciosConIntencion()
    {
        return $this->hasMany(Comercio::class, 'pending_plan_id');
    }
}
