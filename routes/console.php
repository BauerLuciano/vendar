<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\AnalizarStockParaTransferencias;
use App\Jobs\ExpirarPedidosPendientes;
use App\Jobs\GenerarOrdenesCompraSugeridas; 

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// 1. Transferencias entre sucursales
Schedule::job(new AnalizarStockParaTransferencias)->daily()->withoutOverlapping();

// 2. Compras a proveedores (Aquí es donde fallaba)
Schedule::job(new GenerarOrdenesCompraSugeridas)->dailyAt('01:00')->withoutOverlapping();

// El comando se ejecuta todos los días a la madrugada
Schedule::command('cuentas:aplicar-mora')->dailyAt('01:00')->withoutOverlapping();

// El robot de vencimientos revisa los lotes todos los días a las 00:30 AM
Schedule::command('inventario:liquidar-lotes')->dailyAt('00:30')->withoutOverlapping();

// Evalúa promociones automáticas (stock, vencimientos, etc.)
Schedule::command('promotions:evaluate')->dailyAt('02:00')->withoutOverlapping();

// Expirar pedidos web no pagados (cada 15 minutos)
Schedule::job(new ExpirarPedidosPendientes)->everyFifteenMinutes()->withoutOverlapping();