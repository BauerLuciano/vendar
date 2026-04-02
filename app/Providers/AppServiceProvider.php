<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Producto;
use App\Observers\ProductoObserver;
use App\Models\BranchProducto;
use App\Observers\BranchProductoObserver;

use App\Models\IngresoDetalle;
use App\Observers\IngresoDetalleObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Producto::observe(ProductoObserver::class);

        BranchProducto::observe(BranchProductoObserver::class);

        IngresoDetalle::observe(IngresoDetalleObserver::class);
    }
}