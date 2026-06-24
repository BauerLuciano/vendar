<?php

return [
    'types' => ['MANUAL', 'AUTO'],

    'discount_types' => [
        'percent' => 'Porcentaje (%)',
        'fixed_amount' => 'Monto Fijo ($)',
        'fixed_price' => 'Precio Fijo ($)',
        '2x1' => '2x1',
        'bundle' => 'Combo',
        'x_for_y' => 'X por Y',
    ],

    'condition_types' => [
        'product' => 'Producto específico',
        'category' => 'Categoría',
        'brand' => 'Marca',
        'stock' => 'Stock disponible',
        'expiry_date' => 'Próximo a vencer',
        'margin' => 'Margen de ganancia',
        'product_margin' => 'Precio de venta',
    ],

    'evaluators' => [
        'product' => \App\Services\Promotion\Evaluators\ProductEvaluator::class,
        'category' => \App\Services\Promotion\Evaluators\CategoryEvaluator::class,
        'brand' => \App\Services\Promotion\Evaluators\BrandEvaluator::class,
        'stock' => \App\Services\Promotion\Evaluators\StockEvaluator::class,
        'expiry_date' => \App\Services\Promotion\Evaluators\ExpiryDateEvaluator::class,
        'margin' => \App\Services\Promotion\Evaluators\MarginEvaluator::class,
        'product_margin' => \App\Services\Promotion\Evaluators\ProductMarginEvaluator::class,
    ],

    'available_operators' => [
        '=' => 'Igual a',
        '!=' => 'Distinto de',
        '>' => 'Mayor que',
        '<' => 'Menor que',
        '>=' => 'Mayor o igual',
        '<=' => 'Menor o igual',
        'in' => 'En lista',
    ],

    'default_priority' => 0,

    'max_per_product' => 10,
];
