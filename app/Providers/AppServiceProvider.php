<?php

namespace App\Providers;

use App\Facturacion\Application\Arca\ConectividadResolverPorComercio;
use App\Facturacion\Application\Arca\PadronResolverPorComercio;
use App\Facturacion\Application\Arca\WsfetResolverPorComercio;
use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Application\DiagnosticoFiscalService;
use App\Facturacion\Application\EmisionVentaService;
use App\Facturacion\Application\NcService;
use App\Facturacion\Application\VentaOperacionFiscalService;
use App\Facturacion\Application\WizardConfiguracionService;
use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Contracts\PendienteNcRepository;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\Services\EstadoFiscalService;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Certificado\PfxParser;
use App\Facturacion\Infrastructure\Arca\Cifrado\CertificadoEncryptor;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaRepository;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Facturacion\Infrastructure\Arca\Conectividad\ConectividadArcaService;
use App\Facturacion\Infrastructure\Arca\DefaultSoapClientFactory;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\HabilitadorHomologacion;
use App\Facturacion\Infrastructure\Arca\Padron\CondicionFiscalMapper;
use App\Facturacion\Infrastructure\Arca\Padron\PadronClientFactory;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use App\Facturacion\Infrastructure\Arca\Wsaa\FirmaCms;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use App\Facturacion\Infrastructure\Arca\Wsfe\CaeMapper;
use App\Facturacion\Infrastructure\Arca\Wsfe\ComprobanteAsociadoResolver;
use App\Facturacion\Infrastructure\Arca\Wsfe\FECAERequestBuilder;
use App\Facturacion\Infrastructure\Arca\Wsfe\LedgerComprobanteAsociadoResolver;
use App\Facturacion\Infrastructure\Arca\Wsfe\WsfetClientFactory;
use App\Facturacion\Infrastructure\Persistence\EloquentComprobanteFiscalRepository;
use App\Facturacion\Infrastructure\Persistence\EloquentConfiguracionFiscalRepository;
use App\Facturacion\Infrastructure\Persistence\EloquentPendienteNcRepository;
use App\Listeners\LogAuthenticationActivity;
use App\Models\Promotion;
use App\Models\StoreConfig;
use App\Observers\PromotionObserver;
use App\Observers\StoreConfigObserver;
use App\Services\BarcodeLookup\BarcodeLookupProviderRegistry;
use App\Services\OnboardingService;
use App\Services\Payment\PaymentService;
use App\Services\ProductLookupService;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;
use App\Services\Promotion\PromotionConflictResolver;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Promotion\PromotionRuleService;
use App\Services\Promotion\PromotionService;
use App\Services\SucursalScopeService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class);

        $this->app->singleton(BarcodeLookupProviderRegistry::class, function ($app) {
            $registry = new BarcodeLookupProviderRegistry;

            foreach (config('barcode-lookup.providers', []) as $identifier => $providerConfig) {
                if (! ($providerConfig['enabled'] ?? true)) {
                    continue;
                }

                $provider = $app->make($providerConfig['class'], ['config' => $providerConfig]);
                $registry->register($provider);
            }

            return $registry;
        });

        $this->app->singleton(ProductLookupService::class, function ($app) {
            $providers = [];

            foreach (config('product-lookup.providers', []) as $identifier => $config) {
                if (! ($config['enabled'] ?? true)) {
                    continue;
                }

                $providers[] = $app->make($config['class'], ['config' => $config]);
            }

            return new ProductLookupService($providers);
        });

        $this->app->singleton(PromotionConflictResolver::class);

        $this->app->singleton(OnboardingService::class);
        $this->app->singleton(SucursalScopeService::class);

        $this->app->singleton(PromotionService::class);
        $this->app->singleton(PromotionRuleService::class);

        $this->app->singleton(PromotionEngineService::class, function ($app) {
            $engine = new PromotionEngineService($app->make(PromotionConflictResolver::class));

            foreach (config('promotions.evaluators', []) as $conditionType => $evaluatorClass) {
                if (class_exists($evaluatorClass)) {
                    $evaluator = $app->make($evaluatorClass);
                    if ($evaluator instanceof PromotionRuleEvaluator) {
                        $engine->registerEvaluator($conditionType, $evaluator);
                    }
                }
            }

            return $engine;
        });

        $this->registerArcaBindings();
    }

    private function registerArcaBindings(): void
    {
        $this->app->singleton(ArcaEndpointResolver::class, fn () => new ArcaEndpointResolver(config('services.arca', [])));

        $this->app->singleton(SoapClientFactory::class, fn () => new DefaultSoapClientFactory);

        $this->app->singleton(FirmaCms::class);
        $this->app->singleton(CertificadoEncryptor::class);
        $this->app->singleton(PfxParser::class);
        $this->app->singleton(CertificadoService::class);
        $this->app->singleton(CredencialPlataformaRepository::class);
        $this->app->singleton(CredencialPlataformaService::class);
        $this->app->singleton(HabilitadorHomologacion::class);

        $this->app->singleton(WsaaClient::class);
        $this->app->singleton(FECAERequestBuilder::class);
        $this->app->singleton(CaeMapper::class);
        $this->app->singleton(ComprobanteAsociadoResolver::class, LedgerComprobanteAsociadoResolver::class);
        $this->app->singleton(WsfetClientFactory::class);

        $this->app->singleton(CondicionFiscalMapper::class);
        $this->app->singleton(PadronClientFactory::class);

        $this->app->singleton(ConectividadArcaService::class);

        $this->app->singleton(ComprobanteFiscalRepository::class, EloquentComprobanteFiscalRepository::class);
        $this->app->singleton(ConfiguracionFiscalRepository::class, EloquentConfiguracionFiscalRepository::class);
        $this->app->singleton(PendienteNcRepository::class, EloquentPendienteNcRepository::class);
        $this->app->singleton(NumeracionService::class);
        $this->app->singleton(EstadoFiscalService::class);

        $this->app->singleton(DesgloseIvaCalculator::class);
        $this->app->singleton(DeterminacionLetraRule::class);
        $this->app->singleton(ElegibilidadEmisorRule::class);

        $this->app->singleton(WsfetResolver::class, WsfetResolverPorComercio::class);
        $this->app->singleton(PadronResolver::class, PadronResolverPorComercio::class);
        $this->app->singleton(ConectividadResolver::class, ConectividadResolverPorComercio::class);
        $this->app->singleton(WizardConfiguracionService::class);
        $this->app->singleton(EmisionVentaService::class);
        $this->app->singleton(NcService::class);
        $this->app->singleton(VentaOperacionFiscalService::class);
        $this->app->singleton(DiagnosticoFiscalService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('pedidos-web', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        Vite::prefetch(concurrency: 3);

        Event::listen(
            Login::class,
            [LogAuthenticationActivity::class, 'handleLogin']
        );

        Event::listen(
            Logout::class,
            [LogAuthenticationActivity::class, 'handleLogout']
        );

        Promotion::observe(PromotionObserver::class);
        StoreConfig::observe(StoreConfigObserver::class);

        Activity::saving(function (Activity $activity) {
            $props = $activity->properties ?? collect();
            if (! $props->has('ip') && ! $props->has('user_agent')) {
                $activity->properties = $props->merge([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }
}
