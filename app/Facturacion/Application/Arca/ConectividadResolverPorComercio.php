<?php

namespace App\Facturacion\Application\Arca;

use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Conectividad\ConectividadArcaService;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Padron\PadronClientFactory;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use Throwable;

/**
 * Implementación de producción: construye la suite de conectividad con el
 * PadronClient del entorno del comercio y el certificado en memoria
 * (invariante 9). Nunca emite comprobantes de prueba (arquitectura §15).
 */
final class ConectividadResolverPorComercio implements ConectividadResolver
{
    public function __construct(
        private CertificadoService $certificados,
        private WsaaClient $wsaa,
        private SoapClientFactory $transportes,
        private ArcaEndpointResolver $endpoints,
        private PadronClientFactory $padrones,
    ) {}

    public function suite(ConfiguracionFiscal $configuracion): array
    {
        $entorno = EntornoArca::desde($configuracion->entorno());
        $cuit = $configuracion->cuit();

        if ($cuit === null) {
            throw new EmisionVentaException('El comercio no tiene CUIT de emisor para verificar la conexión.');
        }

        try {
            $material = $this->certificados->materialPara($configuracion->comercioId(), $configuracion->entorno());
        } catch (Throwable $e) {
            return [
                [
                    'check' => 'certificado_vigente',
                    'ok' => false,
                    'detalle' => $e->getMessage(),
                ],
            ];
        }

        $servicio = new ConectividadArcaService(
            $this->certificados,
            $this->wsaa,
            $this->transportes,
            $this->endpoints,
            $this->padrones->para($entorno),
        );

        return $servicio->suite($entorno, $material, $cuit);
    }
}
