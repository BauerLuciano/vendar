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
            'wsdl_produccion' => env('ARCA_WSFE_WSDL_PRODUCCION', 'https://servicios1.afip.gov.ar/wsfe/wsfe?WSDL'),
            'wsdl_homologacion' => env('ARCA_WSFE_WSDL_HOMOLOGACION', 'https://wswhomo.afip.gov.ar/wsfe/wsfe?WSDL'),
            'namespace_auth' => env('ARCA_WSFE_NAMESPACE_AUTH', 'http://ar.gov.afip.digifed.wsfe/'),
        ],
        'padron' => [
            'wsdl_produccion' => env('ARCA_PADRON_WSDL_PRODUCCION', 'https://aws.afip.gov.ar/sr-ws-wscdc/ws_sr_constancia_inscripcion?wsdl'),
            'wsdl_homologacion' => env('ARCA_PADRON_WSDL_HOMOLOGACION', 'https://awshomo.afip.gov.ar/sr-ws-wscdc/ws_sr_constancia_inscripcion?wsdl'),
            'namespace_auth' => env('ARCA_PADRON_NAMESPACE_AUTH', 'http://impl.batch.wsaa.afip.gov.ar/'),
        ],
        'soap' => [
            'connection_timeout' => env('ARCA_SOAP_TIMEOUT', 30),
            'cache_wsdl' => env('ARCA_SOAP_CACHE_WSDL', WSDL_CACHE_NONE),
            'exceptions' => true,
            'trace' => env('APP_DEBUG', false),
        ],
    ],
];
