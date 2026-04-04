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
        'codigo_barras',
        'descripcion',
        'unidad_medida',
        'es_retornable',
        'precio_costo',
        'precio_venta',
        'stock_minimo',
        'imagen',
        'estado',
    ];

    protected $casts = [
        'es_retornable' => 'boolean',
        'estado' => 'boolean',
        'precio_costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
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
    
    public function sucursales() {
        return $this->belongsToMany(Sucursal::class, 'producto_sucursal')
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }
}