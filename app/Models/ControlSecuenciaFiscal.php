<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlSecuenciaFiscal extends Model
{
    protected $table = 'control_secuencias_fiscales';

    protected $fillable = [
        'comercio_id',
        'punto_venta',
        'tipo',
        'ultimo_numero',
    ];

    protected $casts = [
        'punto_venta' => 'integer',
        'ultimo_numero' => 'integer',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * Reserva el siguiente número de secuencia bajo lock para evitar duplicados bajo concurrencia.
     */
    public function reservarProximoNumero(): int
    {
        $this->newQuery()
            ->whereKey($this->getKey())
            ->lockForUpdate()
            ->increment('ultimo_numero');

        $this->refresh();

        return $this->ultimo_numero;
    }
}
