<?php

namespace App\Facturacion\Infrastructure\Arca\Conectividad;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Padron\PadronClient;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use Throwable;

/**
 * Suite secuencial de verificación para el botón "Probar conexión con ARCA"
 * (arquitectura §15). Nunca emite comprobantes de prueba en producción:
 * solo salud (FEDummy), certificado, WSAA y padrón.
 */
final class ConectividadArcaService
{
    public function __construct(
        private CertificadoService $certificados,
        private WsaaClient $wsaa,
        private SoapClientFactory $transportes,
        private ArcaEndpointResolver $endpoints,
        private PadronClient $padron,
    ) {}

    /**
     * @return array<int, array{check: string, ok: bool, detalle: string}>
     */
    public function suite(EntornoArca $entorno, CertificadoMaterial $material, Cuit $cuitEmisor): array
    {
        return [
            $this->verificarCertificado($material),
            $this->verificarWsaa($entorno, $material),
            $this->verificarConectividadWsfe($entorno),
            $this->verificarPadron($cuitEmisor),
        ];
    }

    /**
     * @return array{check: string, ok: bool, detalle: string}
     */
    private function verificarCertificado(CertificadoMaterial $material): array
    {
        try {
            $datos = $this->certificados->vigenciaDelMaterial($material);

            if (! $datos->vigente()) {
                return $this->resultado('certificado_vigente', false, 'El certificado está vencido.');
            }

            return $this->resultado('certificado_vigente', true, 'Certificado vigente.');
        } catch (Throwable $e) {
            return $this->resultado('certificado_vigente', false, $e->getMessage());
        }
    }

    /**
     * @return array{check: string, ok: bool, detalle: string}
     */
    private function verificarWsaa(EntornoArca $entorno, CertificadoMaterial $material): array
    {
        try {
            $token = $this->wsaa->obtenerToken('wsfe', $entorno, $material);

            return $this->resultado(
                'wsaa',
                true,
                'Token obtenido hasta '.$token->expiration()->format('d/m/Y H:i:s')
            );
        } catch (Throwable $e) {
            return $this->resultado('wsaa', false, $e->getMessage());
        }
    }

    /**
     * @return array{check: string, ok: bool, detalle: string}
     */
    private function verificarConectividadWsfe(EntornoArca $entorno): array
    {
        try {
            $transporte = $this->transportes->crearTransporte(
                $this->endpoints->wsdlWsfe($entorno),
                $this->endpoints->opcionesSoap()
            );

            $dummy = $transporte->llamar('FEDummy', []);

            $servidor = $dummy->FEDummyResult->appserver ?? $dummy->appserver ?? null;

            if (! is_string($servidor) || $servidor === '') {
                throw new ArcaIntegrationException('FEDummy no devolvió el estado de los servicios.');
            }

            return $this->resultado('conectividad_wsfe', true, 'Servicios de ARCA operativos.');
        } catch (Throwable $e) {
            return $this->resultado('conectividad_wsfe', false, $e->getMessage());
        }
    }

    /**
     * @return array{check: string, ok: bool, detalle: string}
     */
    private function verificarPadron(Cuit $cuitEmisor): array
    {
        try {
            $persona = $this->padron->consultar($cuitEmisor);

            if ($persona['estado'] !== 'ACTIVO') {
                return $this->resultado('padron', false, 'El CUIT no está activo en el padrón.');
            }

            $nombre = $persona['nombre'] ?? $cuitEmisor->valor();

            return $this->resultado('padron', true, "{$nombre} — {$persona['condicion_fiscal']}.");
        } catch (Throwable $e) {
            return $this->resultado('padron', false, $e->getMessage());
        }
    }

    /**
     * @return array{check: string, ok: bool, detalle: string}
     */
    private function resultado(string $check, bool $ok, string $detalle): array
    {
        return ['check' => $check, 'ok' => $ok, 'detalle' => $detalle];
    }
}
