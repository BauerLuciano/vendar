<?php

namespace App\Services\Promotion;

use App\Models\Producto;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;
use App\Services\Promotion\DTOs\AppliedPromotion;
use App\Services\Promotion\DTOs\PromotionData;
use App\Services\Promotion\DTOs\PromotionResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PromotionEngineService
{
    private array $evaluators = [];

    public function __construct(
        private readonly PromotionConflictResolver $conflictResolver,
    ) {}

    public function registerEvaluator(string $conditionType, PromotionRuleEvaluator $evaluator): void
    {
        $this->evaluators[$conditionType] = $evaluator;
    }

    public function forProducto(Producto $producto, ?int $comercioId, ?float $basePrice = null): PromotionResult
    {
        $promotions = collect();

        $manual = $this->getActiveManualPromotions($producto, $comercioId);
        $promotions = $promotions->concat($manual);

        $auto = $this->getActiveAutoPromotions($producto, $comercioId);
        $promotions = $promotions->concat($auto);

        if ($promotions->isEmpty()) {
            return new PromotionResult();
        }

        [$appliedPromotions, $bestPromotion] = $this->conflictResolver->resolve(
            $promotions,
            $basePrice ?? (float) $producto->precio_venta
        );

        if ($bestPromotion === null) {
            return new PromotionResult(
                promotions: $appliedPromotions->map(fn(Promotion $p) => PromotionData::fromModel($p))->all(),
            );
        }

        return new PromotionResult(
            promotions: $appliedPromotions->map(fn(Promotion $p) => PromotionData::fromModel($p))->all(),
            originalPrice: $bestPromotion->originalPrice,
            finalPrice: $bestPromotion->finalPrice,
            discountAmount: $bestPromotion->discountAmount,
            discountLabel: $bestPromotion->discountLabel,
            bestPromotion: $bestPromotion,
        );
    }

    public function forProducts(Collection $products, ?int $comercioId): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $allManual = $this->getAllActiveManualPromotions($comercioId);
        $allAuto = $this->getAllActiveAutoPromotions($comercioId);

        $manualByProductoId = [];
        foreach ($allManual as $promo) {
            foreach ($promo->products as $p) {
                $manualByProductoId[$p->id][] = $promo;
            }
        }

        return $products->map(function (Producto $producto) use ($comercioId, $allManual, $allAuto, $manualByProductoId) {
            $basePrice = (float) $producto->precio_venta;
            $applicablePromos = collect();

            if (isset($manualByProductoId[$producto->id])) {
                foreach ($manualByProductoId[$producto->id] as $promo) {
                    $applicablePromos->push($promo);
                }
            }

            foreach ($allAuto as $promo) {
                if ($this->rulesPass($promo, $producto)) {
                    $applicablePromos->push($promo);
                }
            }

            if ($applicablePromos->isEmpty()) {
                return [
                    'producto' => $producto,
                    'promotion_result' => new PromotionResult(),
                ];
            }

            [$appliedPromos, $bestPromotion] = $this->conflictResolver->resolve($applicablePromos, $basePrice);

            return [
                'producto' => $producto,
                'promotion_result' => new PromotionResult(
                    promotions: $appliedPromos->map(fn(Promotion $p) => PromotionData::fromModel($p))->all(),
                    originalPrice: $bestPromotion?->originalPrice,
                    finalPrice: $bestPromotion?->finalPrice,
                    discountAmount: $bestPromotion?->discountAmount,
                    discountLabel: $bestPromotion?->discountLabel,
                    bestPromotion: $bestPromotion,
                ),
            ];
        });
    }

    private function getAllActiveManualPromotions(?int $comercioId): Collection
    {
        return Promotion::with('products')
            ->active()
            ->where('type', 'MANUAL')
            ->ofComercio($comercioId)
            ->orderByDesc('priority')
            ->get();
    }

    private function getAllActiveAutoPromotions(?int $comercioId): Collection
    {
        return Promotion::with('rules')
            ->active()
            ->where('type', 'AUTO')
            ->ofComercio($comercioId)
            ->orderByDesc('priority')
            ->get();
    }

    private function getActiveManualPromotions(Producto $producto, ?int $comercioId): Collection
    {
        return Promotion::active()
            ->where('type', 'MANUAL')
            ->ofComercio($comercioId)
            ->whereHas('products', fn($q) => $q->where('producto_id', $producto->id))
            ->orderByDesc('priority')
            ->get();
    }

    private function getActiveAutoPromotions(Producto $producto, ?int $comercioId): Collection
    {
        $promotions = Promotion::with('rules')
            ->active()
            ->where('type', 'AUTO')
            ->ofComercio($comercioId)
            ->orderByDesc('priority')
            ->get();

        return $promotions->filter(
            fn(Promotion $promotion) => $this->rulesPass($promotion, $producto)
        );
    }

    private function rulesPass(Promotion $promotion, Producto $producto): bool
    {
        if ($promotion->rules->isEmpty()) {
            return false;
        }

        foreach ($promotion->rules as $rule) {
            if (!$this->evaluateRule($rule, $producto)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateRule(PromotionRule $rule, Producto $producto): bool
    {
        $evaluator = $this->resolverEvaluator($rule->condition_type);

        if ($evaluator === null) {
            Log::warning("PromotionEngine: no evaluator registered for condition_type '{$rule->condition_type}' (rule #{$rule->id})");
            return false;
        }

        return $evaluator->evaluate($rule, $producto);
    }

    private function resolverEvaluator(string $conditionType): ?PromotionRuleEvaluator
    {
        if (isset($this->evaluators[$conditionType])) {
            return $this->evaluators[$conditionType];
        }

        $class = config("promotions.evaluators.{$conditionType}");

        if ($class === null) {
            return null;
        }

        if (!class_exists($class)) {
            Log::warning("PromotionEngine: evaluator class '{$class}' not found for condition_type '{$conditionType}'");
            return null;
        }

        $instance = app($class);

        if (!$instance instanceof PromotionRuleEvaluator) {
            Log::warning("PromotionEngine: evaluator '{$class}' must implement PromotionRuleEvaluator");
            return null;
        }

        $this->evaluators[$conditionType] = $instance;

        return $instance;
    }
}
