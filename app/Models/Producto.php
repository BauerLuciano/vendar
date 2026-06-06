<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Auditable;

class Producto extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'categoria_id',
        'marca_id',
        'proveedor_id',
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
        'precio_promocion',
        'promocion_activa',
        'etiqueta_promocion',
        'promocion_tipo',
        'promocion_fin',
    ];

    protected $casts = [
        'es_retornable' => 'boolean',
        'estado' => 'boolean',
        'precio_costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock_minimo' => 'decimal:3',
        'precio_promocion' => 'decimal:2',
        'promocion_activa' => 'boolean',
        'promocion_fin' => 'date',
    ];

    protected $appends = ['url_imagen', 'sku'];

    public function getUrlImagenAttribute()
    {
        if ($this->imagen) {
            return Storage::url($this->imagen);
        }
        return null;
    }

    public function getSkuAttribute()
    {
        return $this->codigo_barras;
    }

    public function getAhorroAttribute(): ?float
    {
        if (!$this->promocion_activa || $this->precio_promocion === null) {
            return null;
        }
        return round($this->precio_venta - $this->precio_promocion, 2);
    }

    public function getPorcentajeAhorroAttribute(): ?float
    {
        if (!$this->promocion_activa || $this->precio_promocion === null || $this->precio_venta <= 0) {
            return null;
        }
        return round((1 - $this->precio_promocion / $this->precio_venta) * 100, 1);
    }

    public function reglaLiquidacion()
    {
        return $this->hasOne(ReglaLiquidacion::class);
    }

    public function categoria() 
    { 
        return $this->belongsTo(Categoria::class, 'categoria_id'); 
    }

    public function marca() 
    { 
        return $this->belongsTo(Marca::class, 'marca_id'); 
    }

    public function proveedor() 
    { 
        return $this->belongsTo(Proveedor::class, 'proveedor_id'); 
    }
    
    public function sucursales() 
    {
        return $this->belongsToMany(Sucursal::class, 'producto_sucursal', 'producto_id', 'sucursal_id')
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }

    // 🔥 Relación agregada para solucionar el problema de rendimiento (N+1)
    public function lotes()
    {
        return $this->hasMany(Lote::class, 'producto_id');
    }
}