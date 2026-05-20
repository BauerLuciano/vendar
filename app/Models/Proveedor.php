<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Proveedor extends Model
{
    use HasFactory, Auditable;

    protected $table = 'proveedores';

    protected $fillable = [
        'razon_social',
        'cuit',
        'telefono',
        'email',
        'direccion',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];
}