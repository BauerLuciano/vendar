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

    protected $appends = ['url_logo'];

    protected $casts = [
        'modulos_habilitados' => 'array',
        'vencimiento_pago' => 'date',
    ];

    public function getUrlLogoAttribute(): ?string
    {
        return $this->logo ? Storage::url($this->logo) : null;
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
}