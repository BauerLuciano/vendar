<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Auditable;

class Venta extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'turno_caja_id',
        'consumidor_id', 
        'metodo_pago', 
        'total', 
        'estado',
        'motivo_anulacion' 
    ];

    public function turno() { 
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id'); 
    }
    
    public function consumidor() {
        return $this->belongsTo(Consumidor::class); 
    }
    
    public function detalles() {
        return $this->hasMany(DetalleVenta::class); 
    }
}