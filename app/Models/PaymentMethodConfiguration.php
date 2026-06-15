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
}
