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
        return $this->belongsTo(Branch::class, 'origen_id');
    }

    // Relación con la sucursal de destino
    public function destino()
    {
        return $this->belongsTo(Branch::class, 'destino_id');
    }

    // Relación con el producto
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}