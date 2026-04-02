<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $fillable = ['consumidor_id', 'sucursal_id', 'total', 'metodo_pago'];

    public function consumidor() {
        return $this->belongsTo(Consumidor::class); 
    }
    
    public function detalles() {
        return $this->hasMany(VentaDetalle::class); 
    }

    public function sucursal()
    {
        return $this->belongsTo(Branch::class);
    }
}
