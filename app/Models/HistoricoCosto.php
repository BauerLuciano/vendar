<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoCosto extends Model
{
    protected $table = 'historico_costos';

    protected $fillable = [
        'producto_id',
        'costo_anterior',
        'costo_nuevo',
        'precio_venta_anterior',
        'precio_venta_nuevo',
        'user_id',
        'origen_tipo',
        'origen_id',
    ];

    protected $casts = [
        'costo_anterior' => 'decimal:2',
        'costo_nuevo' => 'decimal:2',
        'precio_venta_anterior' => 'decimal:2',
        'precio_venta_nuevo' => 'decimal:2',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
