<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'estado',
    ];

    protected $appends = ['url_imagen'];

    public function getUrlImagenAttribute()
    {
        if ($this->imagen) {
            return Storage::url($this->imagen);
        }
        return null;
    }

    public function categoria() { return $this->belongsTo(Categoria::class, 'categoria_id'); }
    public function marca() { return $this->belongsTo(Marca::class, 'marca_id'); }
    
    public function branches() {
        return $this->belongsToMany(Branch::class, 'branch_producto')
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }

    public function branch_productos()
    {
        return $this->hasMany(BranchProducto::class);
    }
}