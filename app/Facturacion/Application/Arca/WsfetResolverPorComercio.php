<?php

namespace App\Facturacion\Application\Arca;

use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Wsfe\WsfetClientFactory;
use Throwable;

/**
 * Implementación de producción: construye el WsfetClient con el certificado
 * cifrado del comercio (solo en memoria, invariante 9) y su CUIT de emisor.
 */
final class WsfetResolverPorComercio implements WsfetResolver
{
    public function __construct(
        private WsfetClientFactory $factory,
        private CertificadoService $certificados,
    ) {}

    public function resolver(ConfiguracionFiscal $configuracion): Wsfet
    {
        $entorno = $configuracion->entorno();
        $cuit = $configuracion->cuit();

        if ($cuit === null) {
            throw new EmisionVentaException('La configuración fiscal del comercio no tiene CUIT de emisor.');
        }

        try {
            $material = $this->certificados->materialPara($configuracion->comercioId(), $entorno);
        } catch (Throwable $e) {
            throw new EmisionVentaException('No se puede acceder al certificado fiscal del comercio: '.$e->getMessage());
        }

        return $this->factory->para($entorno, $material, $cuit);
    }
}
