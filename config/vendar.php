<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Facturación de suscripciones
    |--------------------------------------------------------------------------
    |
    | Las dos banderas están deshabilitadas por defecto a propósito. VendAR
    | todavía no tiene precios comerciales definitivos, por lo que no se debe
    | registrar un cobro real ni tomar el precio de `planes.precio_mensual`
    | como tarifa oficial. Se habilitarán explícitamente cuando el negocio
    | confirme los precios.
    |
    */
    'facturacion' => [
        'precios_confirmados' => (bool) env('VENDAR_PRECIOS_CONFIRMADOS', false),
        'cobros_habilitados' => (bool) env('VENDAR_COBROS_HABILITADOS', false),
    ],
];
