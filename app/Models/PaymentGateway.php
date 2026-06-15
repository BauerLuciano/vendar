<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $table = 'payment_gateways';

    protected $fillable = [
        'comercio_id',
        'provider',
        'enabled',
        'configuration',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'configuration' => 'array',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }
}
