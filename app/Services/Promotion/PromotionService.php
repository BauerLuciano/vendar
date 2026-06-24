<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PromotionService
{
    public function list(?int $comercioId, array $filters = []): LengthAwarePaginator
    {
        $query = Promotion::query()
            ->withCount('rules')
            ->ofComercio($comercioId);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['active'])) {
            $query->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Promotion
    {
        return Promotion::with(['rules', 'products'])->findOrFail($id);
    }

    public function create(array $data, ?int $comercioId): Promotion
    {
        $data['comercio_id'] = $comercioId;

        return Promotion::create($data);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);

        return $promotion->fresh();
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    public function toggleActive(Promotion $promotion): bool
    {
        $promotion->update(['active' => !$promotion->active]);

        return $promotion->active;
    }

    public function assignProducts(Promotion $promotion, array $productIds): void
    {
        $promotion->products()->syncWithoutDetaching($productIds);
    }

    public function removeProduct(Promotion $promotion, int $productoId): void
    {
        $promotion->products()->detach($productoId);
    }

    public function getAssignedProducts(Promotion $promotion): Collection
    {
        return $promotion->products()->get();
    }
}
