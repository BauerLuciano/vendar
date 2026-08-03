<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnoCaja extends Model
{
    protected $table = 'turno_cajas';

    protected $fillable = [
        'caja_id', 
        'user_id', 
        'sucursal_id',          // Agregado para integridad
        'user_cierre_id',       // Usuario que realiza el arqueo
        'saldo_inicial',        // AGREGADO: Para evitar el error de "Not Null" en DB
        'monto_apertura', 
        'monto_cierre', 
        'saldo_final_efectivo_real', 
        'saldo_final_mp_real', 
        'saldo_final_transf_real', 
        'saldo_final_tarjetas_real', 
        'observaciones_cierre', 
        'fecha_apertura', 
        'fecha_cierre', 
        'estado'
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'monto_apertura' => 'decimal:2',
        'monto_cierre' => 'decimal:2',
    ];

    // Relaciones
    public function caja() 
    { 
        return $this->belongsTo(Caja::class)->withTrashed(); 
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cajero() 
    { 
        return $this->belongsTo(User::class, 'user_id')->withTrashed(); 
    }

    public function usuarioApertura() 
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function usuarioCierre() 
    {
        return $this->belongsTo(User::class, 'user_cierre_id')->withTrashed();
    }

    public function ventas() 
    { 
        return $this->hasMany(Venta::class, 'turno_caja_id'); 
    }

    public function movimientos() 
    {
        return $this->hasMany(MovimientoCaja::class, 'turno_caja_id');
    }
}