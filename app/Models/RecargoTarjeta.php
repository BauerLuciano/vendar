<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecargoTarjeta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recargos_tarjetas';

    protected $fillable = [
        'comercio_id',
        'banco',
        'tipo_tarjeta',
        'cuotas',
        'porcentaje',
        'enabled',
    ];

    protected $casts = [
        'cuotas' => 'integer',
        'porcentaje' => 'decimal:2',
        'enabled' => 'boolean',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }
}
