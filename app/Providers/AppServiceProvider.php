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

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class);
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
