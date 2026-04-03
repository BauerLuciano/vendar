<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\AnalizarStockParaTransferencias;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Analizar stock para transferencias inteligentes (Todos los días)
Schedule::job(new AnalizarStockParaTransferencias)->daily();

// Generar intereses de mora automáticamente (Todos los días)
Schedule::command('app:generar-intereses-mora')->daily();