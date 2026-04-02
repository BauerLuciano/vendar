<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoDetalle extends Model
{
    protected $table = 'ingreso_detalles';

    protected $fillable = [
        'ingreso_id',
        'producto_id',
        'cantidad_recibida',
        'precio_costo_actualizado',
    ];

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(IngresoMercaderia::class, 'ingreso_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}