<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = [
        'comercio_id',
        'name',
        'description',
        'type',
        'discount_type',
        'value',
        'discount_config',
        'starts_at',
        'ends_at',
        'active',
        'priority',
        'exclusive',
        'cumulative',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'discount_config' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
            'priority' => 'integer',
            'exclusive' => 'boolean',
            'cumulative' => 'boolean',
        ];
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'promotion_products')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeOfComercio($query, ?int $comercioId)
    {
        if ($comercioId === null) {
            return $query->whereNull('comercio_id');
        }

        return $query->where(function ($q) use ($comercioId) {
            $q->where('comercio_id', $comercioId)
              ->orWhereNull('comercio_id');
        });
    }

    public function isManual(): bool
    {
        return $this->type === 'MANUAL';
    }

    public function isAuto(): bool
    {
        return $this->type === 'AUTO';
    }

    public function isCurrentlyActive(): bool
    {
        return $this->active
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }
}
