<?php

return [

    'providers' => [
        'openfoodfacts' => [
            'class' => \App\Services\BarcodeLookup\Providers\OpenFoodFactsProvider::class,
            'enabled' => true,
            'endpoints' => [
                'https://world.openfoodfacts.org',
                'https://argentina.openfoodfacts.org',
            ],
            'timeout' => 5,
            'connect_timeout' => 3,
            'user_agent' => 'VendAR - SaaS - 1.0',
        ],

        'eansearch' => [
            'class' => \App\Services\BarcodeLookup\Providers\EanSearchProvider::class,
            'enabled' => env('EANSEARCH_ENABLED', false),
            'api_token' => env('EANSEARCH_API_TOKEN'),
            'timeout' => 5,
            'connect_timeout' => 3,
            'max_requests_per_month' => env('EANSEARCH_MAX_REQUESTS', 100),
            'user_agent' => 'VendAR - SaaS - 1.0',
        ],
    ],

];
