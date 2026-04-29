<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrdenCompra extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'orden_compras';

    protected $fillable = [
        'proveedor_id', 
        'sucursal_id', 
        'user_id', 
        'nro_comprobante', 
        'fecha_emision', 
        'fecha_entrega_esperada', 
        'estado',
        'token_cotizacion', 
        'total_estimado', 
        'observaciones'
    ];

    protected $casts = [
        'fecha_emision'          => 'datetime',
        'fecha_entrega_esperada' => 'date',
        'total_estimado'         => 'decimal:2',
    ];


    public function proveedor() 
    { 
        return $this->belongsTo(Proveedor::class); 
    }

    public function sucursal() 
    { 
        return $this->belongsTo(Sucursal::class); 
    }

    public function usuario() 
    { 
        // Especificamos 'user_id' porque el nombre del método no coincide con la FK
        return $this->belongsTo(User::class, 'user_id'); 
    }
    
    public function detalles() 
    { 
        return $this->hasMany(OrdenCompraDetalle::class); 
    }
}