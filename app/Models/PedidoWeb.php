<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PedidoWeb extends Model
{
    use HasFactory, Auditable;

    // Le decimos exactamente a qué tabla apunta
    protected $table = 'pedidos_web';

    // Todos los campos que permitimos guardar
    protected $fillable = [
        'comercio_id',
        'sucursal_id',
        'tipo_entrega',
        'cliente_nombre',
        'cliente_telefono',
        'cliente_direccion',
        'subtotal',
        'costo_envio',
        'total',
        'metodo_pago',
        'estado_pedido',
        'comprobante_transferencia_url',
        'notas',
    ];

    protected $appends = ['estado_display'];

    public function getEstadoDisplayAttribute(): string
    {
        $labels = [
            'nuevo'      => 'Nuevo',
            'preparando' => $this->tipo_entrega === 'local' ? 'Listo para retirar' : 'En preparación',
            'en_camino'  => 'En camino',
            'entregado'  => 'Entregado',
            'cancelado'  => 'Cancelado',
        ];
        return $labels[$this->estado_pedido] ?? $this->estado_pedido;
    }

    public function nextStates(): array
    {
        $forwardMap = [
            'nuevo'      => 'preparando',
            'preparando' => $this->tipo_entrega === 'local' ? 'entregado' : 'en_camino',
            'en_camino'  => 'entregado',
        ];
        $next = $forwardMap[$this->estado_pedido] ?? null;
        return $next ? [$next] : [];
    }

    // Relación: Este pedido le pertenece a un Comercio
    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    // Relación: Este pedido pertenece a una Sucursal
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function items()
    {
        return $this->hasMany(PedidoWebItem::class, 'pedido_web_id');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}