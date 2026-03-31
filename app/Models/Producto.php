<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    // 1. Damos permiso para guardar masivamente
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

    // 2. Relación: Un Producto PERTENECE A una Categoría
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    // 3. Relación: Un Producto PERTENECE A una Marca
    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }
}