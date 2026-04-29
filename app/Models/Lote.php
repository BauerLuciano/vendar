<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    use HasFactory;

    protected $table = 'lotes';

    protected $fillable = [
        'producto_id',
        'sucursal_id',
        'fecha_vencimiento',
        'stock_inicial',
        'stock_actual',
        'estado_liquidacion',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'estado_liquidacion' => 'boolean',
        'stock_inicial' => 'decimal:2',
        'stock_actual' => 'decimal:2',
    ];

    // Relaciones
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
}