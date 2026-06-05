<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    protected $fillable = [
        'turno_caja_id',
        'tipo',
        'concepto',
        'metodo_pago',
        'monto',
        'descripcion'
    ];

    public function turno()
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    public function getMetodoPagoDisplayAttribute()
    {
        return MetodoPago::fromString($this->metodo_pago)->label();
    }
}