<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Producto extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'sku',
        'categoria_id',
        'marca_id',
        'proveedor_id',
        'nombre',
        'codigo_barras',
        'descripcion',
        'unidad_medida',
        'unidad_compra',
        'cantidad_por_compra',
        'es_retornable',
        'precio_costo',
        'precio_venta',
        'alicuota_iva',
        'precio_venta_actualizado_en',
        'stock_minimo',
        'stock_objetivo',
        'imagen',
        'estado',
    ];

    protected $casts = [
        'es_retornable' => 'boolean',
        'estado' => 'boolean',
        'precio_costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'alicuota_iva' => 'decimal:2',
        'precio_venta_actualizado_en' => 'datetime',
        'stock_minimo' => 'decimal:3',
        'stock_objetivo' => 'decimal:3',
        'cantidad_por_compra' => 'decimal:2',
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

    public function globalProduct()
    {
        return $this->belongsTo(GlobalProduct::class, 'codigo_barras', 'codigo_barras');
    }

    public function lotes()
    {
        return $this->hasMany(Lote::class, 'producto_id');
    }

    public function historicoCostos()
    {
        return $this->hasMany(HistoricoCosto::class, 'producto_id');
    }
}
