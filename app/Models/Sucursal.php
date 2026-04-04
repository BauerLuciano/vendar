<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'tipo',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    // Relación con Productos (La tabla pivot debería llamarse producto_sucursal luego)
    public function productos() {
        return $this->belongsToMany(Producto::class, 'producto_sucursal')
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }
}