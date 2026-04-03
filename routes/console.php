<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\AnalizarStockParaTransferencias;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Nuestro Job programado para correr todos los días
Schedule::job(new AnalizarStockParaTransferencias)->daily();

// Nuestro comando para generar intereses de mora, programado para correr todos los días
Schedule::command('app:generar-intereses-mora')->daily();