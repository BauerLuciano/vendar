<?php

namespace App\Facturacion\Infrastructure\Arca\Wsaa;

use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cliente WSAA (arquitectura §14.1). Obtiene y renueva el ticket de acceso (TA)
 * con una única renovación concurrente: cache con lock por servicio y entorno,
 * más margen de seguridad antes de la expiración.
 */
final class WsaaClient
{
    public const MARGEN_SEGURIDAD_SEGUNDOS = 60;

    public function __construct(
        private SoapClientFactory $transportes,
        private FirmaCms $firma,
        private ArcaEndpointResolver $endpoints,
    ) {}

    public function obtenerToken(string $servicio, EntornoArca $entorno, CertificadoMaterial $material): WsaaToken
    {
        // El TA está firmado con el certificado del comercio: la clave de cache
        // debe distinguir por certificado para que dos comercios con materiales
        // distintos (mismo entorno) nunca compartan token (auditoría F10, H3.1).
        $identificador = substr(hash('sha256', $material->pfx()), 0, 16);
        $clave = "arca.wsaa.{$entorno->value}.{$servicio}.{$identificador}";

        try {
            return Cache::lock($clave, 30)->block(10, function () use ($clave, $servicio, $entorno, $material) {
                $guardado = Cache::get($clave);

                if ($guardado instanceof WsaaToken && ! $guardado->venceAntesDe(self::MARGEN_SEGURIDAD_SEGUNDOS)) {
                    return $guardado;
                }

                $token = $this->solicitar($servicio, $entorno, $material);

                Cache::put($clave, $token, $this->endpoints->ttlWsaa());

                return $token;
            });
        } catch (LockTimeoutException $e) {
            throw new ArcaIntegrationException('No se pudo adquirir el lock de renovación del token de WSAA.', 0, $e);
        }
    }

    private function solicitar(string $servicio, EntornoArca $entorno, CertificadoMaterial $material): WsaaToken
    {
        $ahora = new DateTimeImmutable;
        $expiracion = $ahora->modify('+'.$this->endpoints->ttlWsaa().' seconds');

        $mensaje = $this->mensajeLoginTicket($servicio, $ahora, $expiracion);

        $cms = $this->firma->firmar($material, $mensaje);

        try {
            $respuesta = $this->transportes->crearTransporte(
                $this->endpoints->wsdlWsaa($entorno),
                $this->endpoints->opcionesSoap()
            )->llamar('login', ['in0' => $cms]);
        } catch (ArcaIntegrationException $e) {
            throw new ArcaIntegrationException('Fallo la autenticación en WSAA: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new ArcaIntegrationException('Fallo la autenticación en WSAA: '.$e->getMessage(), 0, $e);
        }

        return $this->tokenDesdeRespuesta($respuesta);
    }

    /**
     * @param  object  $respuesta  Respuesta SOAP de login con loginReturn (XML).
     */
    private function tokenDesdeRespuesta(object $respuesta): WsaaToken
    {
        $loginReturn = $respuesta->loginReturn ?? null;

        if (! is_string($loginReturn)) {
            throw new ArcaIntegrationException('WSAA no devolvió el ticket de acceso.');
        }

        $xml = simplexml_load_string($loginReturn);

        if ($xml === false) {
            throw new ArcaIntegrationException('WSAA devolvió un ticket de acceso inválido.');
        }

        $token = trim((string) ($xml->credentials->token ?? ''));
        $sign = trim((string) ($xml->credentials->sign ?? ''));
        $expiracionTexto = trim((string) ($xml->header->expirationTime ?? ''));

        if ($token === '' || $sign === '' || $expiracionTexto === '') {
            throw new ArcaIntegrationException('WSAA devolvió token, sign o expiración vacíos.');
        }

        try {
            $expiracion = new DateTimeImmutable($expiracionTexto);
        } catch (Throwable $e) {
            throw new ArcaIntegrationException('WSAA devolvió una fecha de expiración inválida.', 0, $e);
        }

        return new WsaaToken($token, $sign, $expiracion);
    }

    private function mensajeLoginTicket(string $servicio, DateTimeImmutable $generado, DateTimeImmutable $expiracion): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>{$generado->getTimestamp()}</uniqueId>
    <generatedAt>{$generado->format('c')}</generatedAt>
    <expirationTime>{$expiracion->format('c')}</expirationTime>
  </header>
  <service>{$servicio}</service>
</loginTicketRequest>
XML;
    }
}
