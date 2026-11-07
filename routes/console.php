<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificación diaria de publicaciones con vigencia vencida para aviso de entrega en biblioteca (HU: Avisos - Task 3)
Schedule::command('reportes:verificar-vencimientos')->daily();
