<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Enums\PaymentChannel;
use App\Models\Configuracion;
use App\Models\Comercio;
use App\Models\PaymentGateway;
use App\Models\PaymentMethodConfiguration;
use App\Models\StoreConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        $user = $request->user();
        $comercio = Comercio::with('paymentGateways')->findOrFail(
            $user->comercio_id ?? $user->branch?->comercio_id
        );

        $comercio->paymentGateways->each(function ($pg) {
            if (isset($pg->configuration['client_secret'])) {
                $pg->configuration['client_secret'] = '***';
            }
        });

        $paymentMethodConfigs = PaymentMethodConfiguration::where('comercio_id', $comercio->id)
            ->get()
            ->map(fn ($pmc) => [
                'id' => $pmc->id,
                'metodo_pago' => $pmc->metodo_pago,
                'metodo_pago_label' => MetodoPago::from($pmc->metodo_pago)->label(),
                'provider' => $pmc->provider,
                'channel' => $pmc->channel->value,
                'display_data' => $pmc->display_data,
                'enabled' => $pmc->enabled,
            ]);

        $storeConfig = StoreConfig::where('comercio_id', $comercio->id)
            ->first()?->config ?? StoreConfig::defaultConfig();

        return Inertia::render('Configuracion/Index', [
            'configuraciones' => $configuraciones,
            'comercio' => $comercio,
            'paymentMethodConfigs' => $paymentMethodConfigs,
            'metodoPagoOptions' => MetodoPago::options(),
            'storeConfig' => $storeConfig,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $comercio = Comercio::findOrFail(
            $user->comercio_id ?? $user->branch?->comercio_id
        );

        $comercio->update($request->only([
            'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
            'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
            'acepta_efectivo',
        ]));

        if ($request->filled('mp_access_token')) {
            $comercio->mp_access_token = $request->mp_access_token;
        }
        if ($request->filled('payway_public_key')) {
            $comercio->payway_public_key = $request->payway_public_key;
        }
        $comercio->save();

        // Sincronizar payment_gateways
        $mpEnabled = $request->boolean('mp_enabled', !empty($comercio->mp_access_token));
        PaymentGateway::updateOrCreate(
            ['comercio_id' => $comercio->id, 'provider' => 'mercadopago'],
            [
                'enabled' => $mpEnabled,
                'configuration' => ['access_token' => $comercio->mp_access_token],
            ]
        );

        $viumiClientId = $request->input('viumi_client_id');
        if ($viumiClientId) {
            $viumiConfig = [
                'client_id' => $viumiClientId,
                'client_secret' => $request->input('viumi_client_secret'),
                'environment' => $request->input('viumi_environment', 'sandbox'),
            ];

            PaymentGateway::updateOrCreate(
                ['comercio_id' => $comercio->id, 'provider' => 'viumi'],
                [
                    'enabled' => $request->boolean('viumi_enabled'),
                    'configuration' => $viumiConfig,
                ]
            );
        }

        $clavesComercio = [
            'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
            'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
            'acepta_efectivo', 'mp_access_token', 'payway_public_key',
            'viumi_enabled', 'viumi_client_id', 'viumi_client_secret', 'viumi_environment',
        ];
        $dataGlobal = $request->except(array_merge(['logo_empresa', 'logo', 'logo_url', 'mp_enabled'], $clavesComercio));

        foreach ($dataGlobal as $clave => $valor) {
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
        }

        if ($request->hasFile('logo')) {
            if ($comercio->logo && Storage::disk('public')->exists($comercio->logo)) {
                Storage::disk('public')->delete($comercio->logo);
            }
            $comercio->logo = $request->file('logo')->store('logos', 'public');
            $comercio->save();
        }

        return redirect()->back()->with('success', 'Configuraciones actualizadas con éxito.');
    }

    public function storePaymentMethodConfig(Request $request)
    {
        $user = $request->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $request->validate([
            'metodo_pago' => 'required|string|in:' . implode(',', MetodoPago::values()),
            'provider' => 'nullable|string|max:100',
            'display_data' => 'nullable|array',
            'display_data.alias' => 'nullable|string|max:255',
            'display_data.cvu' => 'nullable|string|max:255',
            'display_data.cbu' => 'nullable|string|max:255',
            'display_data.banco' => 'nullable|string|max:255',
            'display_data.titular' => 'nullable|string|max:255',
            'enabled' => 'boolean',
        ]);

        PaymentMethodConfiguration::updateOrCreate(
            [
                'comercio_id' => $comercioId,
                'metodo_pago' => $request->metodo_pago,
                'provider' => $request->provider,
            ],
            [
                'channel' => PaymentChannel::MANUAL,
                'display_data' => $request->display_data,
                'enabled' => $request->boolean('enabled', true),
            ]
        );

        return redirect()->back()->with('success', 'Medio de pago configurado.');
    }

    public function destroyPaymentMethodConfig(Request $request, PaymentMethodConfiguration $paymentMethodConfiguration)
    {
        $user = $request->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        if ($paymentMethodConfiguration->comercio_id !== $comercioId) {
            abort(403);
        }

        $paymentMethodConfiguration->delete();

        return redirect()->back()->with('success', 'Configuración eliminada.');
    }
}
