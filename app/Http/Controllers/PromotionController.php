<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Models\Categoria;
use App\Models\Marca;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Promotion\PromotionService;
use App\Services\Promotion\PromotionRuleService;
use App\Services\Promotion\PromotionConflictResolver;
use App\Services\Promotion\DTOs\PromotionData;
use App\Services\Promotion\DTOs\PromotionResult;
use App\Services\Promotion\DTOs\PromotionPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotionService,
        private readonly PromotionRuleService $ruleService,
        private readonly PromotionEngineService $engine,
        private readonly PromotionConflictResolver $conflictResolver,
    ) {}

    public function index(Request $request): Response
    {
        $comercioId = auth()->user()->branch?->comercio_id;

        $promotions = $this->promotionService->list($comercioId, $request->only(['type', 'active', 'search', 'per_page']));

        $promotions->through(fn($p) => PromotionData::fromModel($p)->toArray());

        return Inertia::render('Promotions/Index', [
            'promotions' => $promotions,
            'filters' => $request->only(['type', 'active', 'search']),
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm();
    }

    public function edit(Promotion $promotion): Response
    {
        $this->authorizeAccess($promotion);

        $promotion->load(['rules', 'products']);

        return $this->renderForm($promotion);
    }

    private function renderForm(?Promotion $promotion = null): Response
    {
        $comercioId = auth()->user()->branch?->comercio_id;

        return Inertia::render('Promotions/Create', [
            'promotion' => $promotion ? PromotionData::fromModel($promotion)->toArray() : null,
            'promotionRules' => $promotion?->rules?->toArray() ?? [],
            'promotionProducts' => $promotion?->products?->toArray() ?? [],
            'categories' => Categoria::deComercio($comercioId)->get(['id', 'nombreCategoria']),
            'brands' => Marca::deComercio($comercioId)->get(['id', 'nombreMarca as nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $comercioId = auth()->user()->branch?->comercio_id;

        $validated = $request->validate($this->promotionRules());

        $promotion = $this->promotionService->create($validated, $comercioId);

        if (!empty($validated['product_ids'])) {
            $promotion->products()->sync($validated['product_ids']);
        }

        if (!empty($validated['rules'])) {
            foreach ($validated['rules'] as $ruleData) {
                $promotion->rules()->create($ruleData);
            }
        }

        return redirect()->route('promotions.index')
            ->with('success', 'Promoción creada correctamente.');
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizeAccess($promotion);

        $validated = $request->validate($this->promotionRules($promotion->id));

        $this->promotionService->update($promotion, $validated);

        if ($request->has('product_ids')) {
            $promotion->products()->sync($validated['product_ids'] ?? []);
        }

        if ($request->has('rules')) {
            $promotion->rules()->delete();
            foreach ($validated['rules'] ?? [] as $ruleData) {
                $promotion->rules()->create($ruleData);
            }
        }

        return redirect()->route('promotions.index')
            ->with('success', 'Promoción actualizada correctamente.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorizeAccess($promotion);

        $this->promotionService->delete($promotion);

        return redirect()->back()->with('success', 'Promoción eliminada correctamente.');
    }

    public function toggleActive(Promotion $promotion): RedirectResponse
    {
        $this->authorizeAccess($promotion);

        $this->promotionService->toggleActive($promotion);

        return redirect()->back()->with('success', 'Estado actualizado.');
    }

    public function preview(Request $request): JsonResponse
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        $productIds = $request->input('product_ids', []);

        if (empty($productIds)) {
            return response()->json(new PromotionPreview());
        }

        $discountType = $request->input('discount_type');
        $discountValue = $request->input('value');
        $discountConfig = $request->input('discount_config');

        $sucursalIds = auth()->user()->branch?->comercio?->sucursales?->pluck('id');
        if ($sucursalIds === null || $sucursalIds->isEmpty()) {
            $sucursalIds = auth()->user()->branches?->pluck('id') ?? collect();
        }

        $products = Producto::whereIn('id', $productIds)
            ->when($comercioId && $sucursalIds->isNotEmpty(), function ($q) use ($sucursalIds) {
                $q->whereHas('sucursales', fn($sq) => $sq->whereIn('sucursales.id', $sucursalIds));
            })
            ->get();

        $previews = [];
        $totalOriginal = 0;
        $totalFinal = 0;
        $warnings = [];

        foreach ($products as $product) {
            $basePrice = (float) ($product->precio_venta ?? 0);

            if ($basePrice <= 0) {
                $previews[] = [
                    'product_id' => $product->id,
                    'name' => $product->nombre,
                    'barcode' => $product->codigo_barras,
                    'error' => 'Sin precio disponible',
                ];
                continue;
            }

            $mockPromotion = new Promotion();
            $mockPromotion->discount_type = $discountType;
            $mockPromotion->value = $discountValue;
            $mockPromotion->discount_config = $discountConfig;

            $discount = $this->conflictResolver->resolveDiscount($mockPromotion, $basePrice);
            $final = $this->conflictResolver->calculateFinalPrice($mockPromotion, $basePrice);
            $label = $this->conflictResolver->makeDiscountLabel($mockPromotion, $discount);

            $totalOriginal += $basePrice;
            $totalFinal += $final;

            $previews[] = [
                'product_id' => $product->id,
                'name' => $product->nombre,
                'barcode' => $product->codigo_barras,
                'original_price' => $basePrice,
                'final_price' => $final,
                'discount_amount' => $discount,
                'discount_label' => $label,
            ];
        }

        $result = new PromotionPreview(
            totalProducts: count($previews),
            productPreviews: $previews,
            originalPrice: $totalOriginal,
            finalPrice: $totalFinal,
            discountAmount: $totalOriginal - $totalFinal,
            discountLabel: count($previews) > 0 ? $previews[0]['discount_label'] ?? '' : null,
            warnings: $warnings,
        );

        return response()->json($result->toArray());
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $comercioId = auth()->user()->branch?->comercio_id;

        $query = Producto::query()
            ->with(['categoria', 'marca'])
            ->where('estado', true);

        if ($comercioId) {
            $sucursalIds = auth()->user()->branch?->comercio?->sucursales?->pluck('id') ?? [];
            $query->whereHas('sucursales', fn($q) => $q->whereIn('sucursales.id', $sucursalIds));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                  ->orWhere('codigo_barras', 'ilike', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('categoria_id', $categoryId);
        }

        if ($brandId = $request->input('brand_id')) {
            $query->where('marca_id', $brandId);
        }

        $products = $query->orderBy('nombre')
            ->paginate($request->input('per_page', 20));

        return response()->json($products);
    }

    public function show(int $id): JsonResponse
    {
        $promotion = $this->promotionService->find($id);

        $this->authorizeAccess($promotion);

        return response()->json([
            'promotion' => PromotionData::fromModel($promotion)->toArray(),
            'rules' => $promotion->rules->toArray(),
            'products' => $promotion->products->toArray(),
        ]);
    }

    public function assignProducts(Request $request, Promotion $promotion): JsonResponse
    {
        $this->authorizeAccess($promotion);

        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:productos,id',
        ]);

        $this->promotionService->assignProducts($promotion, $validated['product_ids']);

        return response()->json($promotion->products()->get());
    }

    public function removeProduct(Promotion $promotion, Producto $producto): JsonResponse
    {
        $this->authorizeAccess($promotion);

        $this->promotionService->removeProduct($promotion, $producto->id);

        return response()->json(null, 204);
    }

    public function evaluateProduct(Request $request): JsonResponse
    {
        $comercioId = auth()->user()->branch?->comercio_id;

        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'base_price' => 'nullable|numeric|min:0',
        ]);

        $producto = Producto::findOrFail($request->producto_id);
        $basePrice = $request->base_price ?? (float) ($producto->precio_venta ?? 0);
        $result = $this->engine->forProducto($producto, $comercioId, $basePrice);

        return response()->json($result->toArray());
    }

    private function promotionRules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::in(config('promotions.types'))],
            'discount_type' => ['required', Rule::in(array_keys(config('promotions.discount_types')))],
            'value' => 'nullable|numeric|min:0',
            'discount_config' => 'nullable|array',
            'starts_at' => 'required|date|after_or_equal:today',
            'ends_at' => 'required|date|after:starts_at',
            'active' => 'boolean',
            'priority' => 'integer|min:0',
            'exclusive' => 'boolean',
            'cumulative' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:productos,id',
            'rules' => 'nullable|array',
            'rules.*.condition_type' => ['required_with:rules', Rule::in(config('promotions.condition_types'))],
            'rules.*.operator' => ['required_with:rules', Rule::in(array_keys(config('promotions.available_operators')))],
            'rules.*.value' => 'required_with:rules|string|max:255',
            'rules.*.action_type' => ['required_with:rules', Rule::in(['discount_percent', 'fixed_price'])],
            'rules.*.action_value' => 'required_with:rules|numeric|min:0',
        ];
    }

    private function authorizeAccess(Promotion $promotion): void
    {
        $comercioId = auth()->user()->branch?->comercio_id;

        if ($comercioId && $promotion->comercio_id !== null && $promotion->comercio_id !== $comercioId) {
            abort(403, 'Esta promoción no pertenece a tu comercio.');
        }
    }
}
