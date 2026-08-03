<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraDetalle extends Model
{
    protected $fillable = [
        'orden_compra_id', 'producto_id', 'cantidad_pedida',
        'cantidad_recibida', 'costo_unitario_estimado', 'subtotal_estimado',
        'fecha_vencimiento',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
    ];

    public function ordenCompra() { return $this->belongsTo(OrdenCompra::class)->withTrashed(); }
    public function producto() { return $this->belongsTo(Producto::class); }
}