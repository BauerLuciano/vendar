<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';

    protected $fillable = [
        'producto_id',
        'sucursal_id',
        'user_id',
        'tipo_movimiento',
        'cantidad_anterior',
        'cantidad_movimiento',
        'cantidad_actual',
        'motivo',
    ];

    protected $casts = [
        'cantidad_anterior' => 'decimal:3',
        'cantidad_movimiento' => 'decimal:3',
        'cantidad_actual' => 'decimal:3',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
