<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogAuthenticationActivity;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use App\Services\Payment\PaymentService;
use App\Services\BarcodeLookup\BarcodeLookupProviderRegistry;
use App\Services\ProductLookupService;
use App\Services\Promotion\PromotionService;
use App\Services\Promotion\PromotionRuleService;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Promotion\PromotionConflictResolver;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;
use App\Models\Promotion;
use App\Models\StoreConfig;
use App\Observers\PromotionObserver;
use App\Observers\StoreConfigObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class);

        $this->app->singleton(BarcodeLookupProviderRegistry::class, function ($app) {
            $registry = new BarcodeLookupProviderRegistry();

            foreach (config('barcode-lookup.providers', []) as $identifier => $providerConfig) {
                if (!($providerConfig['enabled'] ?? true)) {
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
                if (!($config['enabled'] ?? true)) {
                    continue;
                }

                $providers[] = $app->make($config['class'], ['config' => $config]);
            }

            return new ProductLookupService($providers);
        });

        $this->app->singleton(PromotionConflictResolver::class);

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
    }

    public function boot(): void
    {
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
            if (!$props->has('ip') && !$props->has('user_agent')) {
                $activity->properties = $props->merge([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }
}
