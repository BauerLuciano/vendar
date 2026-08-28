<?php

namespace App\Console\Commands;

use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Obtiene el Token y Sign de WSAA en HOMOLOGACIÓN a partir del pfx del comercio.
 * Solo uso manual para pruebas: imprime el ticket de acceso sin persistirlo.
 */
class WsaaObtenerTokenCommand extends Command
{
    protected $signature = 'arca:wsaa-token {pfx : Ruta al archivo pfx del certificado} {--password= : Contraseña del pfx (si se omite, se pide por consola)}';

    protected $description = 'Obtiene el Token y Sign de WSAA en HOMOLOGACIÓN para el servicio ws_sr_constancia_inscripcion';

    public function handle(WsaaClient $wsaa): int
    {
        $ruta = $this->argument('pfx');

        if (! is_file($ruta) || ! is_readable($ruta)) {
            $this->error("El archivo pfx no existe o no es legible: {$ruta}");

            return Command::FAILURE;
        }

        $password = $this->option('password')
            ?? $this->secret('Contraseña del pfx');

        if ($password === null || $password === '') {
            $this->error('La contraseña del pfx es obligatoria.');

            return Command::FAILURE;
        }

        try {
            $material = new CertificadoMaterial((string) file_get_contents($ruta), $password);

            $token = $wsaa->obtenerToken('ws_sr_constancia_inscripcion', EntornoArca::HOMOLOGACION, $material);
        } catch (CertificadoInvalidoException|ArcaIntegrationException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error inesperado: '.$e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Token de acceso de WSAA (HOMOLOGACIÓN) obtenido correctamente.');
        $this->line('Token:');
        $this->line($token->token());
        $this->line('Sign:');
        $this->line($token->sign());
        $this->line('Expiración: '.$token->expiration()->format('Y-m-d H:i:s'));

        return Command::SUCCESS;
    }
}
