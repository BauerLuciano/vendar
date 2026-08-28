<?php

namespace App\Console\Commands;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use Illuminate\Console\Command;
use Throwable;

class CargarCredencialPlataforma extends Command
{
    protected $signature = 'arca:credencial-plataforma {cuit : CUIT de la credencial de plataforma} {token : Token WSAA} {sign : Sign WSAA}';

    protected $description = 'Guarda (encriptada) la credencial de plataforma de VendAR para la consulta al padrón ARCA';

    public function handle(CredencialPlataformaService $credencial): int
    {
        try {
            $credencial->guardar(
                new Cuit($this->argument('cuit')),
                $this->argument('token'),
                $this->argument('sign'),
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Credencial de plataforma guardada correctamente.');

        return Command::SUCCESS;
    }
}
