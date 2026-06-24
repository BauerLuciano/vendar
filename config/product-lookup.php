<?php

return [

    'providers' => [
        'openfoodfacts' => [
            'class' => \App\Services\ProductLookup\Providers\OpenFoodFactsProvider::class,
            'enabled' => true,
            'endpoints' => [
                'https://world.openfoodfacts.org',
                'https://argentina.openfoodfacts.org',
            ],
            'timeout' => 5,
            'connect_timeout' => 3,
            'user_agent' => 'VendAR - SaaS - 1.0',
        ],
    ],

];
