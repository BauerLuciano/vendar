<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraHistorial extends Model
{
    protected $table = 'orden_compra_historial';

    protected $fillable = [
        'orden_compra_id',
        'estado',
        'user_id',
        'motivo',
        'detalle',
    ];

    protected $casts = [
        'detalle' => 'array',
    ];

    public function ordenCompra()
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
