<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Enums\PaymentChannel;
use App\Models\Configuracion;
use App\Models\Comercio;
use App\Models\Sucursal;
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
        $user = $request->user();
        $comercio = Comercio::with('paymentGateways')->findOrFail(
            $user->comercio_id ?? $user->branch?->comercio_id
        );

        $configuraciones = Configuracion::paraComercio($comercio->id);

        $tieneNombrePropio = Configuracion::where('comercio_id', $comercio->id)
            ->where('clave', 'nombre_empresa')
            ->exists();

        if (!$tieneNombrePropio) {
            $configuraciones['nombre_empresa'] = $comercio->nombre ?? 'Mi Negocio';
        }

        $tieneDireccionPropia = Configuracion::where('comercio_id', $comercio->id)
            ->where('clave', 'direccion')
            ->exists();

        if (!$tieneDireccionPropia) {
            $configuraciones['direccion'] = $this->direccionPrimeraSucursal($comercio->id);
        }

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
                'metodo_pago_label' => $pmc->provider ? $pmc->provider : MetodoPago::from($pmc->metodo_pago)->label(),
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

        $modulosHabilitados = $comercio->modulos_habilitados ?? ['pos' => true];
        $tienePedidosWeb = !empty($modulosHabilitados['pedidos_web']);

        // Campos exclusivos de e-commerce (pestaña "Tienda Online y Pagos").
        // Sin el módulo pedidos_web se descartan en backend aunque se envíen por POST directo.
        $camposEcommerce = [
            'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
            'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
            'mp_access_token', 'mp_enabled', 'payway_public_key',
            'viumi_client_id', 'viumi_client_secret', 'viumi_environment', 'viumi_enabled',
        ];

        if (!$tienePedidosWeb) {
            foreach ($camposEcommerce as $campo) {
                $request->request->remove($campo);
            }
        }

        $request->validate([
            'transferencia_cbu' => 'nullable|string|regex:/^[0-9]*$/|max:22',
            'transferencia_alias' => 'nullable|string|max:50',
            'transferencia_titular' => 'nullable|string|regex:/^[\pL\s\.\,]*$/u|max:100',
            'envio_precio_base' => 'nullable|numeric|min:0',
            'envio_precio_km' => 'nullable|numeric|min:0',
            'envio_radio_km' => 'nullable|numeric|min:0',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'mp_access_token' => 'nullable|string|max:255',
            'mp_enabled' => 'nullable|boolean',
            'payway_public_key' => 'nullable|string|max:255',
            'viumi_client_id' => 'nullable|string|max:255',
            'viumi_client_secret' => 'nullable|string|max:255',
            'viumi_environment' => 'nullable|string|in:sandbox,production',
            'viumi_enabled' => 'nullable|boolean',
        ], [
            'transferencia_cbu.regex' => 'El CBU solo puede contener números.',
            'transferencia_titular.regex' => 'El nombre del titular solo puede contener letras.',
            'logo.image' => 'El archivo debe ser una imagen válida.',
            'logo.mimes' => 'El logo debe ser jpg, png o webp.',
            'logo.max' => 'El logo no debe superar los 2MB.',
        ]);

        $comercio->update($request->only([
            'envio_precio_base', 'envio_precio_km', 'envio_radio_km',
            'transferencia_cbu', 'transferencia_alias', 'transferencia_titular',
            'acepta_efectivo',
        ]));

        if ($tienePedidosWeb && $request->filled('mp_access_token')) {
            $comercio->mp_access_token = $request->mp_access_token;
        }
        if ($tienePedidosWeb && $request->filled('payway_public_key')) {
            $comercio->payway_public_key = $request->payway_public_key;
        }
        $comercio->save();

        // Sincronizar payment_gateways (solo si el comercio tiene tienda online)
        if ($tienePedidosWeb) {
            $mpEnabled = $request->boolean('mp_enabled', !empty($comercio->mp_access_token));
            PaymentGateway::updateOrCreate(
                ['comercio_id' => $comercio->id, 'provider' => 'mercadopago'],
                [
                    'enabled' => $mpEnabled,
                    'configuration' => ['access_token' => $comercio->mp_access_token],
                ]
            );
        }

        $viumiClientId = $tienePedidosWeb ? $request->input('viumi_client_id') : null;
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

        // --- Configuraciones globales (tabla configuraciones) ---
        // Whitelist explicita de claves permitidas con sus validaciones
        $globalConfigKeys = [
            'nombre_empresa'            => 'nullable|string|max:255',
            'cuit'                      => 'nullable|string|regex:/^[\d\-]*$/|max:20',
            'razon_social'              => 'nullable|string|max:255',
            'condicion_iva'             => 'nullable|string|in:responsable_inscripto,monotributista,exento,no_inscripto,consumidor_final,otro,',
            'telefono'                  => 'nullable|string|regex:/^[\d\s\-\(\)\+]*$/|max:20',
            'direccion'                 => 'nullable|string|max:255',
            'ticket_mensaje_pie'        => 'nullable|string|max:500',
            'formato_impresion'         => 'nullable|string|in:58mm,80mm,A4',
            'ticket_digital_auto_email' => 'nullable|in:0,1,true,false',
            'permitir_stock_negativo'   => 'nullable|in:0,1,true,false',
            'limite_fiado_defecto'      => 'nullable|numeric|min:0',
            'moneda_defecto'            => 'nullable|string|in:ARS,USD,EUR',
            'costo_delivery_defecto'    => 'nullable|numeric|min:0',
            'mora_dias_gracia'          => 'nullable|integer|min:0|max:365',
            'mora_tasa_interes'         => 'nullable|numeric|min:0|max:100',
        ];

        $request->validate($globalConfigKeys, [
            'cuit.regex'     => 'El CUIT/RUT solo puede contener números.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'telefono.max'   => 'El teléfono no debe superar los 15 dígitos.',
        ]);

        foreach ($globalConfigKeys as $clave => $rules) {
            if ($request->has($clave)) {
                $valor = $request->input($clave);
                if (in_array($clave, ['cuit', 'telefono'], true)) {
                    $valor = preg_replace('/\D/', '', $valor);
                }
                Configuracion::updateOrCreate(
                    ['comercio_id' => $comercio->id, 'clave' => $clave],
                    ['valor' => $valor]
                );
            }
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
            'display_data' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $metodo = MetodoPago::tryFrom((string) $request->metodo_pago);
                    if ($metodo?->grupo() === 'Transferencias'
                        && empty($value['alias'])
                        && empty($value['cvu'])
                        && empty($value['cbu'])) {
                        $fail('Ingresá al menos un Alias o CVU/CBU.');
                    }
                },
            ],
            'display_data.alias' => 'nullable|string|max:255',
            'display_data.cvu' => 'nullable|string|regex:/^[0-9]*$/|max:22',
            'display_data.cbu' => 'nullable|string|regex:/^[0-9]*$/|max:22',
            'display_data.banco' => 'nullable|string|regex:/^[\pL\s\.]*$/u|max:255',
            'display_data.titular' => 'nullable|string|regex:/^[\pL\s\.\,]*$/u|max:255',
            'enabled' => 'boolean',
        ], [
            'display_data.cvu.regex' => 'El CVU solo puede contener números.',
            'display_data.cbu.regex' => 'El CBU solo puede contener números.',
            'display_data.banco.regex' => 'El banco solo puede contener letras.',
            'display_data.titular.regex' => 'El nombre del titular solo puede contener letras.',
        ]);

        $metodoPago = MetodoPago::from($request->metodo_pago);
        $esTransferencia = $metodoPago->grupo() === 'Transferencias';

        PaymentMethodConfiguration::updateOrCreate(
            [
                'comercio_id' => $comercioId,
                'metodo_pago' => $request->metodo_pago,
                'provider' => $request->provider,
            ],
            [
                'channel' => PaymentChannel::MANUAL,
                'display_data' => $esTransferencia ? $request->display_data : null,
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

    // Reutiliza la dirección física cargada en la primera sucursal del comercio
    // (la que se registra al crear la cuenta o la primera creada por el dueño).
    private function direccionPrimeraSucursal(int $comercioId): ?string
    {
        $sucursal = Sucursal::where('comercio_id', $comercioId)
            ->whereNotNull('direccion')
            ->where('direccion', '!=', '')
            ->where('direccion', '!=', 'Dirección a definir')
            ->orderBy('id')
            ->first();

        return $sucursal?->direccion;
    }
}
