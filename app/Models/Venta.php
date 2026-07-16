<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Enums\VentaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Auditable;
use App\Models\PaymentMethodConfiguration;

class Venta extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'turno_caja_id',
        'consumidor_id', 
        'metodo_pago', 
        'pagos',
        'total', 
        'recargo_monto',
        'estado',
        'motivo_anulacion' 
    ];

    protected $casts = [
        'pagos' => 'array',
        'recargo_monto' => 'decimal:2',
        'estado' => VentaStatus::class,
    ];

    public function turno() { 
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id'); 
    }
    
    public function consumidor() {
        return $this->belongsTo(Consumidor::class); 
    }
    
    public function detalles() {
        return $this->hasMany(DetalleVenta::class); 
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getMetodoPagoDisplayAttribute()
    {
        $comercioId = $this->turno?->caja?->sucursal?->comercio_id;
        if ($this->metodo_pago === 'MULTIPLE' && $this->pagos) {
            return collect($this->pagos)
                ->map(fn ($p) => PaymentMethodConfiguration::resolveDisplayLabel($p['metodo_pago'], $comercioId) . ': $' . number_format($p['monto'], 2))
                ->implode(' + ');
        }
        return PaymentMethodConfiguration::resolveDisplayLabel($this->metodo_pago, $comercioId);
    }

    public function getPagosDisplayAttribute()
    {
        $comercioId = $this->turno?->caja?->sucursal?->comercio_id;
        if (!$this->pagos) {
            return [['metodo_pago' => $this->metodo_pago, 'monto' => $this->total, 'label' => $this->metodo_pago_display]];
        }
        return collect($this->pagos)->map(fn ($p) => [
            'metodo_pago' => $p['metodo_pago'],
            'monto' => $p['monto'],
            'label' => PaymentMethodConfiguration::resolveDisplayLabel($p['metodo_pago'], $comercioId),
        ])->toArray();
    }
}