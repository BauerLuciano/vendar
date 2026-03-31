<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaCorriente extends Model
{
    use HasFactory;

    protected $table = 'cuentas_corrientes';

    protected $fillable = [
        'consumidor_id',
        'saldo_deudor',
        'fecha_ultimo_movimiento',
        'estado',
    ];

    // Relación: Esta cuenta pertenece a UN consumidor
    public function consumidor()
    {
        return $this->belongsTo(Consumidor::class);
    }
}