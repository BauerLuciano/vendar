<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReglaLiquidacion extends Model
{
    use HasFactory;

    protected $table = 'reglas_liquidaciones';

    protected $fillable = [
        'producto_id',
        'dias_anticipacion',
        'porcentaje_descuento',
        'estado',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}