<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'payable_id',
        'payable_type',
        'provider',
        'channel',
        'status',
        'gateway_transaction_id',
        'authorization_code',
        'reference',
        'provider_reference',
        'attempt',
        'amount',
        'currency',
        'gateway_request',
        'gateway_response',
        'metadata',
        'approved_at',
        'failed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_request' => 'array',
            'gateway_response' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isApproved(): bool
    {
        return $this->status === PaymentStatus::APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === PaymentStatus::REJECTED;
    }
}
