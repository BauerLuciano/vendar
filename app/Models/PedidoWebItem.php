<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoWebItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pedido_web_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'subtotal'
    ];

    public function pedido()
    {
        return $this->belongsTo(PedidoWeb::class, 'pedido_web_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}