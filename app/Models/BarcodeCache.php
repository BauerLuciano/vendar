<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeCache extends Model
{
    protected $fillable = [
        'codigo_barras',
        'nombre',
        'marca',
        'categoria',
        'presentacion',
        'imagen',
        'descripcion',
    ];
}
