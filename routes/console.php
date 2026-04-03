<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\AnalizarStockParaTransferencias;
use App\Jobs\GenerarOrdenesCompraSugeridas; 

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// 1. Transferencias entre sucursales
Schedule::job(new AnalizarStockParaTransferencias)->daily();

// 2. Compras a proveedores (Aquí es donde fallaba)
Schedule::job(new GenerarOrdenesCompraSugeridas)->dailyAt('01:00');

// 3. Intereses por mora
Schedule::command('app:generar-intereses-mora')->daily();