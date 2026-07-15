<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodConfiguration extends Model
{
    protected $fillable = [
        'comercio_id',
        'metodo_pago',
        'provider',
        'channel',
        'display_data',
        'enabled',
    ];

    protected $casts = [
        'channel' => PaymentChannel::class,
        'display_data' => 'array',
        'enabled' => 'boolean',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function metodoPago(): MetodoPago
    {
        return MetodoPago::from($this->metodo_pago);
    }

    public static function resolveDisplayLabel(string $metodoPago, ?int $comercioId): string
    {
        $baseLabel = MetodoPago::fromString($metodoPago)->label();
        if (!$comercioId) return $baseLabel;

        $provider = static::where('comercio_id', $comercioId)
            ->where('metodo_pago', $metodoPago)
            ->where('enabled', true)
            ->value('provider');

        return $provider ? $provider : $baseLabel;
    }

    public static function labelMap(int $comercioId): array
    {
        $configs = static::where('comercio_id', $comercioId)
            ->where('enabled', true)
            ->pluck('provider', 'metodo_pago')
            ->toArray();

        $map = [];
        foreach ($configs as $metodo => $provider) {
            $base = MetodoPago::fromString($metodo)->label();
            $map[$metodo] = $provider ? $provider : $base;
        }
        return $map;
    }
}
