<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria_id',
        'marca_id',
        'nombre',
        'sku',
        'descripcion',
        'precio_costo',
        'precio_venta',
        'stock_minimo',
        'imagen',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_producto')
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }

    public function reglasLiquidacion()
    {
        return $this->hasMany(ReglaLiquidacion::class);
    }
}