<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReposicionSugerida extends Model
{
    protected $table = 'reposiciones_sugeridas';

    protected $fillable = [
        'comercio_id',
        'sucursal_id',
        'producto_id',
        'estado',
        'ignorado_hasta',
    ];

    protected $casts = [
        'ignorado_hasta' => 'datetime',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
