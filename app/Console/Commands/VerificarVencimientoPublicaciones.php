<?php

namespace App\Console\Commands;

use App\Services\AvisoService;
use Illuminate\Console\Command;

class VerificarVencimientoPublicaciones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reportes:verificar-vencimientos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica publicaciones aprobadas con vigencia vencida y genera avisos para entrega en biblioteca';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Verificando publicaciones con periodo de vigencia vencido...');

        $generados = AvisoService::procesarVencimientosPendientes();

        $this->info("Proceso completado. Avisos generados: {$generados}.");

        return Command::SUCCESS;
    }
}

