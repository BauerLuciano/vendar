<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferenciaSugerida extends Model
{
    use HasFactory;

    protected $fillable = [
        'origen_id',
        'destino_id',
        'producto_id',
        'cantidad',
        'estado',
    ];

    // Relación con la sucursal de origen
    public function origen()
    {
        // CAMBIAMOS Branch::class por Sucursal::class
        return $this->belongsTo(Sucursal::class, 'origen_id');
    }

    // Relación con la sucursal de destino
    public function destino()
    {
        // CAMBIAMOS Branch::class por Sucursal::class
        return $this->belongsTo(Sucursal::class, 'destino_id');
    }

    // Relación con el producto
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}