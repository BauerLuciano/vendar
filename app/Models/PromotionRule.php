<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRule extends Model
{
    protected $fillable = [
        'promotion_id',
        'condition_type',
        'operator',
        'value',
        'action_type',
        'action_value',
    ];

    protected function casts(): array
    {
        return [
            'action_value' => 'decimal:2',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
