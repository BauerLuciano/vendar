<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consumidor extends Model
{
    use HasFactory;

    protected $table = 'consumidores';

    protected $fillable = [
        'nombre',
        'dni',
        'telefono',
        'direccion',
        'limite_cuenta_corriente',
    ];

    // Magia: Cuando se crea un Consumidor, le creamos su Cuenta Corriente en 0
    protected static function booted()
    {
        static::created(function ($consumidor) {
            $consumidor->cuentaCorriente()->create([
                'saldo_deudor' => 0,
                'estado' => true,
            ]);
        });
    }

    public function cuentaCorriente()
    {
        return $this->hasOne(CuentaCorriente::class);
    }
}