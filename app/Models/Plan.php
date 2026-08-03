<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio_mensual',
        'modulos',
        'sucursales_limit',
        'usuarios_limit',
        'trial_dias',
        'trial_activo',
        'dias_mora',
        'destacado',
        'orden',
        'activo',
    ];

    protected $casts = [
        'modulos' => 'array',
        'precio_mensual' => 'decimal:2',
        'destacado' => 'boolean',
        'activo' => 'boolean',
        'trial_dias' => 'integer',
        'trial_activo' => 'boolean',
        'dias_mora' => 'integer',
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
