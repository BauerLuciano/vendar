<?php

namespace App\Services\Promotion;

use App\Models\Producto;
use App\Services\Promotion\DTOs\PromotionPreview;
use Illuminate\Support\Collection;

class PromotionPreviewService
{
    public function generate(
        Collection $products,
        string $discountType,
        ?float $discountValue,
        ?array $discountConfig,
    ): PromotionPreview {
        if ($products->isEmpty()) {
            return new PromotionPreview();
        }

        return match ($discountType) {
            '2x1' => $this->simulateXForY($products, 2, 1),
            'x_for_y' => $this->simulateXForY(
                $products,
                (int) ($discountConfig['x'] ?? 2),
                (int) ($discountConfig['y'] ?? 1),
            ),
            'bundle' => $this->simulateBundle($products, $discountConfig),
            default => $this->simulatePerUnit($products, $discountType, $discountValue),
        };
    }

    private function simulatePerUnit(
        Collection $products,
        string $discountType,
        ?float $discountValue,
    ): PromotionPreview {
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

            $discount = $this->calcUnitDiscount($discountType, $discountValue, $basePrice);
            $final = $this->calcUnitFinal($discountType, $discountValue, $basePrice);

            $totalOriginal += $basePrice;
            $totalFinal += $final;

            $previews[] = [
                'product_id' => $product->id,
                'name' => $product->nombre,
                'barcode' => $product->codigo_barras,
                'unit_price' => $basePrice,
                'original_price' => $basePrice,
                'final_price' => $final,
                'discount_amount' => $discount,
                'discount_label' => $this->unitLabel($discountType, $discountValue, $discount),
                'quantity' => 1,
            ];
        }

        return new PromotionPreview(
            totalProducts: count($previews),
            productPreviews: $previews,
            originalPrice: $totalOriginal,
            finalPrice: $totalFinal,
            discountAmount: $totalOriginal - $totalFinal,
            discountLabel: $this->typeLabel($discountType, $discountValue),
            discountType: $discountType,
            explanation: $this->perUnitExplanation($discountType, $discountValue),
            warnings: $warnings,
        );
    }

    private function simulateXForY(Collection $products, int $x, int $y): PromotionPreview
    {
        $product = $products->first();
        $basePrice = (float) ($product->precio_venta ?? 0);

        if ($basePrice <= 0 || $x <= 0 || $y <= 0 || $y >= $x) {
            return new PromotionPreview(warnings: ['No se puede simular esta promoción']);
        }

        $original = $basePrice * $x;
        $final = $basePrice * $y;
        $discount = $original - $final;

        $previews = [[
            'product_id' => $product->id,
            'name' => $product->nombre,
            'barcode' => $product->codigo_barras,
            'unit_price' => $basePrice,
            'original_price' => $original,
            'final_price' => $final,
            'discount_amount' => $discount,
            'discount_label' => "{$x}x{$y}",
            'quantity' => $x,
        ]];

        return new PromotionPreview(
            totalProducts: 1,
            productPreviews: $previews,
            originalPrice: $original,
            finalPrice: $final,
            discountAmount: $discount,
            discountLabel: "{$x}x{$y}",
            discountType: 'x_for_y',
            explanation: $this->xForYExplanation($x, $y),
        );
    }

    private function simulateBundle(Collection $products, ?array $config): PromotionPreview
    {
        $comboPrice = (float) ($config['price'] ?? 0);
        $previews = [];
        $totalOriginal = 0;

        foreach ($products as $product) {
            $basePrice = (float) ($product->precio_venta ?? 0);
            $totalOriginal += $basePrice;

            $previews[] = [
                'product_id' => $product->id,
                'name' => $product->nombre,
                'barcode' => $product->codigo_barras,
                'original_price' => $basePrice,
                'final_price' => null,
                'discount_amount' => null,
                'discount_label' => null,
            ];
        }

        $discount = max(0, $totalOriginal - $comboPrice);

        $previews[] = [
            'product_id' => 0,
            'name' => 'Precio Combo',
            'barcode' => '',
            'original_price' => $totalOriginal,
            'final_price' => $comboPrice,
            'discount_amount' => $discount,
            'discount_label' => 'Combo',
            'is_summary' => true,
        ];

        return new PromotionPreview(
            totalProducts: count($products),
            productPreviews: $previews,
            originalPrice: $totalOriginal,
            finalPrice: $comboPrice,
            discountAmount: $discount,
            discountLabel: 'Combo',
            discountType: 'bundle',
            explanation: $this->bundleExplanation(count($products), $comboPrice),
        );
    }

    private function calcUnitDiscount(string $type, ?float $value, float $price): float
    {
        return match ($type) {
            'percent' => $price * ((float) $value / 100),
            'fixed_amount' => (float) $value,
            'fixed_price' => max(0, $price - (float) $value),
            default => 0,
        };
    }

    private function calcUnitFinal(string $type, ?float $value, float $price): float
    {
        if ($type === 'fixed_price') {
            return max(0, min($price, (float) $value));
        }
        return max(0, $price - $this->calcUnitDiscount($type, $value, $price));
    }

    private function unitLabel(string $type, ?float $value, float $discount): string
    {
        return match ($type) {
            'percent' => "-{$value}%",
            'fixed_amount' => "-\${$discount}",
            'fixed_price' => '$' . number_format((float) $value, 2),
            default => 'Descuento',
        };
    }

    private function typeLabel(string $type, ?float $value): string
    {
        return match ($type) {
            'percent' => "-{$value}%",
            'fixed_amount' => "-\${$value}",
            'fixed_price' => '$' . number_format((float) $value, 2),
            '2x1' => '2x1',
            'x_for_y' => 'x_for_y',
            'bundle' => 'Combo',
            default => 'Descuento',
        };
    }

    private function perUnitExplanation(string $type, ?float $value): string
    {
        $val = number_format((float) $value, 0, ',', '.');
        return match ($type) {
            'percent' => "Se aplicará un {$value}% de descuento sobre el precio de venta.",
            'fixed_amount' => "Se descontarán \${$val} del precio de venta.",
            'fixed_price' => "El producto tendrá un precio final de \${$val}.",
            default => '',
        };
    }

    private function xForYExplanation(int $x, int $y): string
    {
        $savings = $x - $y;
        return "El cliente lleva {$x} unidades y paga solo {$y}. Se ahorra {$savings} unidad" . ($savings > 1 ? 'es' : '') . ".";
    }

    private function bundleExplanation(int $count, float $price): string
    {
        $formatted = number_format($price, 0, ',', '.');
        return "Comprando los {$count} productos incluidos, el cliente pagará \${$formatted} en total.";
    }
}
