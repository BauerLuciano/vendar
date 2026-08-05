<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $fillable = ['venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'alicuota_iva', 'subtotal', 'cantidad_devuelta'];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function lotes()
    {
        return $this->belongsToMany(Lote::class, 'detalle_venta_lote')->withPivot('cantidad');
    }
}
