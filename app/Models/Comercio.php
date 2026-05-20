<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Comercio extends Model
{
    use Auditable;
    protected $table = 'comercios';

    protected $fillable = [
        'nombre',
        'slug',
        'plan',
        'status',
        'limite_sucursales',
        'vencimiento_pago',
        'modulos_habilitados',
        'envio_precio_base', 
        'envio_precio_km', 
        'envio_radio_km',
        'transferencia_cbu', 
        'transferencia_alias', 
        'transferencia_titular',
        'mp_access_token', 
        'payway_public_key', 
        'acepta_efectivo',
    ];

    protected $casts = [
        'modulos_habilitados' => 'array',
        'vencimiento_pago' => 'date',
    ];

    public function sucursales()
    {
        return $this->hasMany(Sucursal::class, 'comercio_id');
    }

    public function pedidosWeb()
    {
        return $this->hasMany(PedidoWeb::class);
    }

}