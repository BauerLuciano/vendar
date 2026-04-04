<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoMercaderia extends Model
{
    protected $table = 'ingresos_mercaderias';
    
    protected $fillable = [
        'proveedor_id', 
        'sucursal_id', 
        'user_id',
        'numero_remito', 
        'fecha_ingreso', 
        'total_costo'
    ];

    public function proveedor() { 
        return $this->belongsTo(Proveedor::class, 'proveedor_id'); 
    }
    
    public function sucursal() { 
        return $this->belongsTo(Sucursal::class, 'sucursal_id'); 
    }
    
    public function detalles() { 
        return $this->hasMany(IngresoDetalle::class, 'ingreso_mercaderia_id'); 
    }

    public function usuario() { 
    return $this->belongsTo(User::class, 'user_id'); 
}
}