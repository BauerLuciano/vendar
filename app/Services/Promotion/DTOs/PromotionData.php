<?php

namespace App\Services\Promotion\DTOs;

use App\Models\Promotion;

class PromotionData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $type,
        public readonly string $discountType,
        public readonly ?float $value,
        public readonly ?array $discountConfig,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly bool $active,
        public readonly int $priority,
        public readonly bool $exclusive,
        public readonly bool $cumulative,
        public readonly ?int $comercioId,
        public readonly int $rulesCount = 0,
    ) {}

    public static function fromModel(Promotion $promotion): self
    {
        return new self(
            id: $promotion->id,
            name: $promotion->name,
            description: $promotion->description,
            type: $promotion->type,
            discountType: $promotion->discount_type,
            value: $promotion->value !== null ? (float) $promotion->value : null,
            discountConfig: $promotion->discount_config,
            startsAt: $promotion->starts_at->toIso8601String(),
            endsAt: $promotion->ends_at->toIso8601String(),
            active: $promotion->active,
            priority: $promotion->priority,
            exclusive: $promotion->exclusive,
            cumulative: $promotion->cumulative,
            comercioId: $promotion->comercio_id,
            rulesCount: $promotion->rules_count ?? $promotion->rules?->count() ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'discount_type' => $this->discountType,
            'value' => $this->value,
            'discount_config' => $this->discountConfig,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'active' => $this->active,
            'priority' => $this->priority,
            'exclusive' => $this->exclusive,
            'cumulative' => $this->cumulative,
            'comercio_id' => $this->comercioId,
            'rules_count' => $this->rulesCount,
        ];
    }
}
