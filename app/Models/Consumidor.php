<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Auditable;

class Consumidor extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

    protected $table = 'consumidores';

    protected $fillable = [
        'comercio_id',
        'nombre',
        'apellido',
        'documento',
        'email',
        'telefono',
        'direccion',
        'limite_cuenta_corriente',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'limite_cuenta_corriente' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::created(function ($consumidor) {
            $consumidor->cuentaCorriente()->create([
                'saldo_deudor' => 0,
                'estado' => true,
            ]);
        });
    }

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function cuentaCorriente()
    {
        return $this->hasOne(CuentaCorriente::class);
    }

    public function scopeDeComercio($query, $comercioId)
    {
        return $query->where('comercio_id', $comercioId);
    }
}