<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoDetalle extends Model
{
    protected $table = 'ingreso_detalles';
    
    protected $fillable = [
        'ingreso_mercaderia_id', 
        'producto_id', 
        'cantidad_recibida', 
        'costo_unitario'
    ];

    public function ingreso() { 
        return $this->belongsTo(IngresoMercaderia::class, 'ingreso_mercaderia_id'); 
    }
    
    public function producto() { 
        return $this->belongsTo(Producto::class, 'producto_id'); 
    }
}