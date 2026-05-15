<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Comercio;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class SuscripcionController extends Controller
{
    public function miPlan()
    {
        $comercio = Comercio::find(auth()->user()->comercio_id);

        if (!$comercio) {
            $comercio = (object)[
                'id' => 999,
                'nombre' => 'Local de Pruebas',
                'plan' => 'basico',
                'status' => 'activo'
            ];
        }

        return Inertia::render('Suscripcion/MiPlan', [
            'comercio' => $comercio,
            'planes' => [
                ['id' => 'basico', 'nombre' => 'Plan Básico', 'precio' => 8000, 'color' => '#94a3b8', 'caracteristicas' => ['Punto de Venta Base', '1 Sucursal', 'Soporte por Email']],
                ['id' => 'pro', 'nombre' => 'Plan Profesional', 'precio' => 15000, 'color' => '#00adef', 'caracteristicas' => ['Stock Avanzado', '3 Sucursales', 'Proveedores', 'Soporte prioritario']],
                ['id' => 'premium', 'nombre' => 'Plan Premium', 'precio' => 35000, 'color' => '#8cc63f', 'caracteristicas' => ['Auditoría Completa', 'Sucursales Ilimitadas', 'Fiados', 'Optimización Stock']],
            ]
        ]);
    }

    /**
     * Genera la orden de pago en MP y devuelve la URL del checkout
     */
    public function generarPreferencia(Request $request)
    {
        try {
            $request->validate(['plan_id' => 'required|in:basico,pro,premium']);

            $comercioId = auth()->user()->comercio_id ?? 7;
            $precios = ['basico' => 8000, 'pro' => 15000, 'premium' => 35000];
            $monto = $precios[$request->plan_id];

            // 1. Configuración de Mercado Pago con tu nuevo Token APP_USR
            $token = trim(env('MERCADOPAGO_ACCESS_TOKEN'));
            MercadoPagoConfig::setAccessToken($token);
            
            // Esto es vital en Laragon/Windows para evitar errores de SSL
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

            $client = new PreferenceClient();
            
            // Definimos la URL base para los retornos
            $baseUrl = "http://localhost:8000"; // Asegurate que sea la que usás en el navegador

            // 2. Creamos la preferencia
            $preference = $client->create([
                "items" => [
                    [
                        "id" => "sub-" . $request->plan_id,
                        "title" => "VendAR: Plan " . strtoupper($request->plan_id),
                        "quantity" => 1,
                        "unit_price" => (float) $monto,
                        "currency_id" => "ARS"
                    ]
                ],
                "back_urls" => [
                    "success" => "{$baseUrl}/mi-plan?pago=exito",
                    "failure" => "{$baseUrl}/mi-plan?pago=error",
                    "pending" => "{$baseUrl}/mi-plan?pago=pendiente"
                ],
                // Nota: Dejamos auto_return desactivado para testear en localhost sin HTTPS
                // "auto_return" => "approved", 
                "external_reference" => (string)$comercioId,
                "binary_mode" => true, // Pago aceptado o rechazado, sin estados intermedios
            ]);

            // Devolvemos el init_point para que el frontend abra la pasarela
            return response()->json([
                'init_point' => $preference->init_point
            ]);

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            return response()->json([
                'error' => 'Error de API MP',
                'detalle_real_mp' => $e->getApiResponse()->getContent()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error general',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}