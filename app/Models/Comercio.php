<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Illuminate\Support\Facades\Storage;

class Comercio extends Model
{
    use Auditable;
    protected $table = 'comercios';

    protected $fillable = [
        'nombre',
        'slug',
        'logo',
        'plan',
        'plan_id',
        'status',
        'pending_plan_id',
        'limite_sucursales',
        'limite_usuarios',
        'vencimiento_pago',
        'modulos_habilitados',
        'envio_precio_base',
        'envio_precio_km',
        'envio_radio_km',
        'transferencia_cbu',
        'transferencia_alias',
        'transferencia_titular',
        'acepta_efectivo',
    ];

    protected $hidden = [
        'mp_access_token',
        'payway_public_key',
    ];

    protected $appends = ['url_logo', 'tiene_mp'];

    protected $casts = [
        'modulos_habilitados' => 'array',
        'vencimiento_pago' => 'date',
    ];

    public function getUrlLogoAttribute(): ?string
    {
        return $this->logo ? Storage::url($this->logo) : null;
    }

    public function getTieneMpAttribute(): bool
    {
        return !empty($this->mp_access_token);
    }

    public function paymentGateways()
    {
        return $this->hasMany(PaymentGateway::class, 'comercio_id');
    }

    public function paymentMethodConfigurations()
    {
        return $this->hasMany(PaymentMethodConfiguration::class, 'comercio_id');
    }

    public function sucursales()
    {
        return $this->hasMany(Sucursal::class, 'comercio_id');
    }

    public function pedidosWeb()
    {
        return $this->hasMany(PedidoWeb::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function pendingPlan()
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function storeConfig()
    {
        return $this->hasOne(StoreConfig::class, 'comercio_id');
    }
}