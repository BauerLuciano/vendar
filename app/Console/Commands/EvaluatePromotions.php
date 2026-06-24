<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Models\Producto;
use App\Models\Promotion;
use App\Services\Promotion\PromotionEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluatePromotions extends Command
{
    protected $signature = 'promotions:evaluate {--comercio_id=} {--product_id=}';

    protected $description = 'Evalúa promociones automáticas y aplica las que correspondan';

    public function handle(PromotionEngineService $engine): int
    {
        $this->info('Evaluando promociones automáticas...');

        $comercioId = $this->option('comercio_id');
        $productId = $this->option('product_id');

        $autoPromotions = Promotion::active()
            ->where('type', 'AUTO')
            ->when($comercioId, fn($q) => $q->ofComercio($comercioId))
            ->get();

        if ($autoPromotions->isEmpty()) {
            $this->info('No hay promociones automáticas activas.');
            return self::SUCCESS;
        }

        $this->info("Se encontraron {$autoPromotions->count()} promociones automáticas activas.");

        $products = Producto::with('sucursales')
            ->when($productId, fn($q) => $q->where('id', $productId))
            ->when($comercioId, function ($q) use ($comercioId) {
                $comercio = Comercio::find($comercioId);
                if ($comercio) {
                    $sucursalIds = $comercio->sucursales()->pluck('id');
                    $q->whereHas('sucursales', fn($sq) => $sq->whereIn('sucursales.id', $sucursalIds));
                }
            })
            ->get();

        $this->info("Evaluando contra {$products->count()} productos...");

        $evaluated = 0;
        $matched = 0;

        foreach ($products as $producto) {
            foreach ($autoPromotions as $promotion) {
                $rulesPass = true;

                foreach ($promotion->rules as $rule) {
                    $evaluator = null;
                    $evaluatorClass = config("promotions.evaluators.{$rule->condition_type}");

                    if ($evaluatorClass && class_exists($evaluatorClass)) {
                        $evaluator = app($evaluatorClass);
                    }

                    if ($evaluator === null) {
                        Log::warning("EvaluatePromotions: no evaluator for '{$rule->condition_type}'");
                        $rulesPass = false;
                        break;
                    }

                    if (!$evaluator->evaluate($rule, $producto)) {
                        $rulesPass = false;
                        break;
                    }
                }

                $evaluated++;

                if ($rulesPass) {
                    $matched++;
                    $this->line("  ✓ Producto #{$producto->id} ({$producto->nombre}) coincide con promoción #{$promotion->id} ({$promotion->name})");

                    if (!$promotion->products()->where('producto_id', $producto->id)->exists()) {
                        $promotion->products()->syncWithoutDetaching($producto->id);
                        $this->line("    → Producto asignado a promoción manualmente para su aplicación en POS.");
                    }
                }
            }
        }

        $this->info("Evaluación completa: {$evaluated} reglas evaluadas, {$matched} coincidencias encontradas.");

        return self::SUCCESS;
    }
}
