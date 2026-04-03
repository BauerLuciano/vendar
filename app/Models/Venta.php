<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = ['consumidor_id', 'branch_id', 'user_id', 'total', 'metodo_pago', 'fecha'];

    public function consumidor() {
        return $this->belongsTo(Consumidor::class); 
    }
    
    public function detalles() {
        return $this->hasMany(VentaDetalle::class); 
    }

    // Relación con el vendedor
    public function user() {
        return $this->belongsTo(User::class);
    }

    // Cambié sucursal_id por branch_id para que sea coherente con tu tabla 'branches'
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}