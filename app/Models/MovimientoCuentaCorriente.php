<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoCuentaCorriente extends Model
{
    use HasFactory;

    protected $table = 'movimientos_cuenta_corrientes';

    protected $fillable = [
        'cuenta_corriente_id',
        'monto',
        'tipo',
        'descripcion',
    ];
}