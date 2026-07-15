<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\PaymentMethodConfiguration;
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
        $comercioId = $this->turno?->caja?->sucursal?->comercio_id;
        return PaymentMethodConfiguration::resolveDisplayLabel($this->metodo_pago, $comercioId);
    }
}