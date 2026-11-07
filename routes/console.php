<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificación diaria de publicaciones con vigencia vencida para aviso de entrega en biblioteca (HU: Avisos - Task 3)
Schedule::command('reportes:verificar-vencimientos')->daily();
Artisan::command('reporte:simular-vencimiento {id : ID de la publicacion} {--restaurar : Restaura la vigencia de la publicacion}', function ($id) {
    $pub = \App\Models\Publicacion::find($id);

    if (! $pub) {
        $this->error("No se encontró ninguna publicación con ID: {$id}");
        return 1;
    }

    if ($this->option('restaurar')) {
        $pub->fecha_vencimiento = \Carbon\Carbon::now(\App\Models\Publicacion::TIMEZONE)->addDays(14);
        $pub->disponible = true;
        $pub->save();

        $this->info("Publicación [{$pub->folio} - {$pub->nombre}] RESTAURADA COMO VIGENTE.");
        $this->line("Nueva fecha de vencimiento: {$pub->fecha_vencimiento->format('Y-m-d H:i:s')} (America/Merida)");
        $this->line("Ahora vuelve a aparecer en el muro y en las búsquedas.");
        return 0;
    }

    $pub->fecha_vencimiento = \Carbon\Carbon::now(\App\Models\Publicacion::TIMEZONE)->subMinutes(5);
    $pub->save();

    $this->warn("Publicación [{$pub->folio} - {$pub->nombre}] MARCADA COMO VENCIDA.");
    $this->line("Fecha de vencimiento fijada al pasado: {$pub->fecha_vencimiento->format('Y-m-d H:i:s')} (America/Merida)");
    $this->line("Resultado inmediato: Ha quedado fuera del muro y su enlace directo mostrará:");
    $this->line("«Esta publicación no está disponible para consulta»");
    $this->line("\nPara volver a restaurarla después de tomar tus capturas, ejecuta:");
    $this->line("php artisan reporte:simular-vencimiento {$id} --restaurar");

    return 0;
})->purpose('Simula el vencimiento de una publicación para evidenciar el Caso B de HU-16');

Artisan::command('reporte:listar-candidatos', function () {
    $reportes = \App\Models\Publicacion::with('user')
        ->where('estado', \App\Models\Publicacion::ESTADO_APROBADA)
        ->latest('id')
        ->take(10)
        ->get();

    if ($reportes->isEmpty()) {
        $this->warn('No hay publicaciones aprobadas en la base de datos.');
        return 0;
    }

    $this->info("Publicaciones aprobadas disponibles en tu base de datos local:\n");
    $headers = ['ID', 'Folio', 'Nombre', 'Autor', 'Dev. en proceso', 'Vencida?'];
    $rows = [];

    foreach ($reportes as $r) {
        $rows[] = [
            $r->id,
            $r->folio,
            \Illuminate\Support\Str::limit($r->nombre ?? $r->titulo, 25),
            $r->user?->name ?? 'N/A',
            $r->devolucion_en_proceso ? 'SÍ' : 'NO',
            $r->isVencida() ? 'VENCIDA' : 'VIGENTE',
        ];
    }

    $this->table($headers, $rows);
    return 0;
})->purpose('Muestra publicaciones candidatas para evidenciar HU-16');

Artisan::command('publicaciones:procesar-vencimientos', function () {
    $ahoraMerida = \Carbon\Carbon::now(\App\Models\Publicacion::TIMEZONE);
    $ahoraUtc = $ahoraMerida->copy()->setTimezone(config('app.timezone', 'UTC'));

    $vencidas = \App\Models\Publicacion::where('estado', \App\Models\Publicacion::ESTADO_APROBADA)
        ->whereNotNull('fecha_vencimiento')
        ->where('fecha_vencimiento', '<=', $ahoraUtc)
        ->get();

    $procesadas = 0;
    foreach ($vencidas as $pub) {
        if ($pub->correspondeEntregaBiblioteca()) {
            if ($pub->despacharAvisoVencimientoSiCorresponde()) {
                $procesadas++;
            }
        }
    }

    $this->info("Proceso de vencimientos ejecutado. Avisos despachados: {$procesadas}");
    return 0;
})->purpose('Proceso programado institucional para procesar publicaciones vencidas y avisos (HU-16)');

Artisan::command('reporte:mostrar-calculo {id}', function ($id) {
    $r = \App\Models\Publicacion::find($id);
    if (! $r) {
        $this->error("No se encontró publicación con ID: {$id}");
        return 1;
    }

    $tz = \App\Models\Publicacion::TIMEZONE;
    $this->info("=== DETALLE DE FECHAS PARA COMPROBAR CÁLCULO (HU-16) ===");
    $this->line("Folio: {$r->folio} - {$r->nombre}");
    $this->line("Fecha de Captura: " . ($r->fecha_captura ? $r->fecha_captura->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : 'N/A'));
    $this->line("Fecha de Hallazgo: " . ($r->fecha_hallazgo ? $r->fecha_hallazgo->format('d/m/Y') : 'N/A') . " a las " . ($r->hora_hallazgo ?? 'N/A'));
    $this->line("Primera Aprobación: " . ($r->fecha_primera_aprobacion ? $r->fecha_primera_aprobacion->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : 'N/A'));
    $this->line("Fecha de Vencimiento: " . ($r->fecha_vencimiento ? $r->fecha_vencimiento->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : 'N/A'));

    if ($r->fecha_primera_aprobacion && $r->fecha_vencimiento) {
        $diffDias = $r->fecha_primera_aprobacion->copy()->setTimezone($tz)->diffInDays($r->fecha_vencimiento->copy()->setTimezone($tz));
        $this->info("\nDiferencia exacta calculada: {$diffDias} días naturales a la misma hora en {$tz}.");
    }

    return 0;
})->purpose('Muestra fechas exactas de un reporte para evidenciar el cálculo');
