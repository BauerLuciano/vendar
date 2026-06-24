<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalProduct extends Model
{
    protected $table = 'global_products';

    protected $fillable = [
        'codigo_barras',
        'nombre',
        'marca',
        'categoria',
        'presentacion',
        'peso_gramos',
        'imagen',
        'descripcion',
        'fabricante',
        'pais_origen',
        'provider',
        'datos_extra',
    ];

    protected $casts = [
        'peso_gramos' => 'decimal:2',
        'datos_extra' => 'array',
    ];
}
