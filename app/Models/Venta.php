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

    /**
     * Identidad del cliente tal como la ve un usuario del sistema.
     *
     * El modelo admite cuatro situaciones y no conviene suponer una sola:
     *
     *  1. Venta sin `consumidor_id` (pago directo en mostrador) → el
     *     consumidor genérico.
     *  2. Venta con un `Consumidor` cuyo nombre completo es "Consumidor
     *     Final" (el registro genérico que siembran los seeders) → también
     *     se presenta como el consumidor genérico, no como un cliente real.
     *  3. Venta con un cliente identificado → se muestra nombre y apellido.
     *  4. Venta abonada a cuenta corriente → es un cliente identificado cuyo
     *     pago fue a la cuenta corriente del mismo; se marca aparte porque
     *     para el usuario implicó fiado.
     *
     * No altera ninguna lógica comercial: sólo lee lo que ya está grabado.
     *
     * @return array{nombre: string, es_consumidor_final: bool, es_cuenta_corriente: bool, documento: ?string}
     */
    public function presentacionCliente(): array
    {
        $nombre = $this->consumidor
            ? trim(($this->consumidor->nombre ?? '').' '.($this->consumidor->apellido ?? ''))
            : '';

        // El registro genérico se siembran con nombre "Consumidor" y apellido
        // "Final". Comparamos el nombre completo ya concatenado, sin inventar
        // una bandera nueva en la base.
        $esGenerico = $nombre === '' || strcasecmp($nombre, 'Consumidor Final') === 0;

        $esCuentaCorriente = collect($this->pagos_display)
            ->contains(fn ($p) => $p['metodo_pago'] === MetodoPago::CUENTA_CORRIENTE->value);

        return [
            'nombre' => $esGenerico ? 'Consumidor Final' : $nombre,
            'es_consumidor_final' => $esGenerico,
            'es_cuenta_corriente' => $esCuentaCorriente,
            'documento' => $esGenerico ? null : ($this->consumidor->documento ?? null),
        ];
    }
}