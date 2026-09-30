<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'geoapify' => [
        'key' => env('GEOAPIFY_API_KEY'),
    ],

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'public_url' => env('MP_PUBLIC_URL', env('APP_URL')),
        // Orígenes permitidos para los back_urls de retorno de pago (suscripción).
        // El origin que envía el frontend debe coincidir exactamente (scheme + host
        // + puerto) con uno de estos. En runtime se agregan además los orígenes
        // derivados de APP_URL y MP_PUBLIC_URL. Parametrizable vía
        // MP_ALLOWED_RETURN_ORIGINS (separados por coma).
        'allowed_return_origins' => env('MP_ALLOWED_RETURN_ORIGINS')
            ? explode(',', env('MP_ALLOWED_RETURN_ORIGINS'))
            : ['http://localhost', 'http://127.0.0.1', 'http://vendar-app.test'],
    ],
];
