<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'comercio_id', // 🔥 NUEVO: El puente hacia el tenant (Comercio)
        'nombre',
        'direccion',
        'telefono',
        'tipo',
        'estado',
        'latitud', 
        'longitud', 
    ];

    protected $casts = [
        'estado' => 'boolean',
        'latitud' => 'float', 
        'longitud' => 'float', 
    ];

    // 🔥 NUEVA RELACIÓN: Esta sucursal le pertenece a un Comercio
    public function comercio()
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }

    public function productos() {
        return $this->belongsToMany(Producto::class, 'producto_sucursal')
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }

    public function scopeCercanasA($query, $lat, $lng)
    {
        $haversine = "(6371 * acos(cos(radians($lat)) 
                     * cos(radians(latitud)) 
                     * cos(radians(longitud) - radians($lng)) 
                     + sin(radians($lat)) 
                     * sin(radians(latitud))))";

        return $query->select('*')
                     ->selectRaw("$haversine AS distancia")
                     ->orderBy('distancia');
    }
}