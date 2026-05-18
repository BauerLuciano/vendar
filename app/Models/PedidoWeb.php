<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoWeb extends Model
{
    use HasFactory;

    // Le decimos exactamente a qué tabla apunta
    protected $table = 'pedidos_web';

    // Todos los campos que permitimos guardar
    protected $fillable = [
        'comercio_id',
        'cliente_nombre',
        'cliente_telefono',
        'cliente_direccion',
        'subtotal',
        'costo_envio',
        'total',
        'metodo_pago',
        'estado_pago',
        'estado_pedido',
        'comprobante_transferencia_url',
        'pasarela_payment_id',
        'notas'
    ];

    // Relación: Este pedido le pertenece a un Comercio
    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function items()
    {
        return $this->hasMany(PedidoWebItem::class, 'pedido_web_id');
    }
}