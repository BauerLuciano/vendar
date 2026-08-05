<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendienteNc extends Model
{
    protected $table = 'nc_pendientes';

    protected $fillable = [
        'comercio_id',
        'venta_id',
        'tipo_operacion',
        'motivo',
        'items',
        'monto_devuelto',
        'estado',
        'motivo_fallo',
        'intentos',
    ];

    protected $casts = [
        'items' => 'array',
        'monto_devuelto' => 'decimal:2',
        'intentos' => 'integer',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function esPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}
