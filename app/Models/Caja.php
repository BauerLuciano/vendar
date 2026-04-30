<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 

class Caja extends Model 
{
    use SoftDeletes;

    protected $fillable = ['sucursal_id', 'nombre', 'estado'];
    
    protected $casts = [
        'estado' => 'boolean',
    ];
    
    public function sucursal() 
    { 
        return $this->belongsTo(Sucursal::class); 
    }
    
    public function turnos() 
    { 
        return $this->hasMany(TurnoCaja::class); 
    }

    public function puedeEliminarse(): bool
    {
        return !$this->turnos()->exists();
    }
}