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

    /*
    |--------------------------------------------------------------------------
    | ARCA (Facturación Electrónica)
    |--------------------------------------------------------------------------
    |
    | Endpoints oficiales de ARCA por entorno (productivo/homologación) para
    | WSAA, WSFE y el padrón (ws_sr_constancia_inscripcion). La credencial de
    | plataforma se guarda cifrada en la tabla de configuración global y nunca
    | se expone al comercio (arquitectura §14.4, invariante 10).
    |
    */

    'arca' => [
        'wsaa' => [
            'wsdl_produccion' => env('ARCA_WSAA_WSDL_PRODUCCION', 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL'),
            'wsdl_homologacion' => env('ARCA_WSAA_WSDL_HOMOLOGACION', 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL'),
            'ttl_segundos' => env('ARCA_WSAA_TTL_SEGUNDOS', 600),
        ],
        'wsfe' => [
            'wsdl_produccion' => env('ARCA_WSFE_WSDL_PRODUCCION', 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL'),
            'wsdl_homologacion' => env('ARCA_WSFE_WSDL_HOMOLOGACION', 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL'),
            'namespace_auth' => env('ARCA_WSFE_NAMESPACE_AUTH', 'http://ar.gov.afip.dif.FEV1/'),
        ],
        'padron' => [
            'wsdl_produccion' => env('ARCA_PADRON_WSDL_PRODUCCION', 'https://aws.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL'),
            'wsdl_homologacion' => env('ARCA_PADRON_WSDL_HOMOLOGACION', 'https://awshomo.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL'),
            'namespace_auth' => env('ARCA_PADRON_NAMESPACE_AUTH', 'http://a5.soap.ws.server.puc.sr/'),
        ],
        'soap' => [
            'connection_timeout' => env('ARCA_SOAP_TIMEOUT', 30),
            'cache_wsdl' => env('ARCA_SOAP_CACHE_WSDL', WSDL_CACHE_NONE),
            'exceptions' => true,
            'trace' => env('APP_DEBUG', false),
        ],
    ],
];
