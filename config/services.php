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
        // Base pública usada para el notification_url que Mercado Pago llama
        // desde sus servidores (webhook). Si MP_PUBLIC_URL está vacía se usa
        // APP_URL en lugar de quedar en string vacío y generar URLs relativas.
        'public_url' => env('MP_PUBLIC_URL') ?: env('APP_URL'),
        // Orígenes permitidos para el retorno del checkout de suscripción.
        // El origin que envía el frontend debe coincidir (scheme + host +
        // puerto) con uno de estos. Los defaults se conservan aunque se defina
        // MP_ALLOWED_RETURN_ORIGINS, para no perder localhost al agregar la IP
        // de la red local. En runtime también se agregan APP_URL y MP_PUBLIC_URL.
        'allowed_return_origins' => array_values(array_unique(array_filter(array_merge(
            ['http://localhost', 'http://127.0.0.1', 'http://vendar-app.test'],
            array_map('trim', explode(',', (string) env('MP_ALLOWED_RETURN_ORIGINS', ''))),
        )))),
    ],
];
