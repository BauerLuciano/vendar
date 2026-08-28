<?php

namespace App\Facturacion\Infrastructure\Arca\Padron;

use App\Facturacion\Domain\Contracts\PadronConsulta;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Exceptions\CredencialPlataformaNoConfiguradaException;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use SoapVar;

/**
 * Cliente del padrón de contribuyentes (ws_sr_constancia_inscripcion,
 * personaServiceA5, manual ARCA v4.1) con credencial de plataforma de VendAR
 * (arquitectura §14.3). La credencial solo consulta el padrón y nunca emite
 * comprobantes (invariante 10).
 *
 * Una instancia se construye por entorno (PadronClientFactory): la consulta del
 * wizard/diagnóstico usa el entorno del comercio.
 */
final class PadronClient implements PadronConsulta
{
    public function __construct(
        private SoapClientFactory $transportes,
        private ArcaEndpointResolver $endpoints,
        private CredencialPlataformaService $credencial,
        private CondicionFiscalMapper $mapper,
        private EntornoArca $entorno,
    ) {}

    public function consultar(Cuit $cuit): array
    {
        $credencial = $this->credencial->leer();

        if ($credencial === null) {
            throw new CredencialPlataformaNoConfiguradaException(
                'La credencial de plataforma no está configurada en Administración Global.'
            );
        }

        $transporte = $this->transportes->crearTransporte(
            $this->endpoints->wsdlPadron($this->entorno),
            $this->endpoints->opcionesSoap()
        );

        $respuesta = $transporte->llamar('getPersona_v2', [new SoapVar($credencial->parametrosConsulta($cuit), SOAP_ENC_OBJECT)]);

        $persona = $respuesta->getPersona_v2Return ?? $respuesta->personaReturn ?? null;

        if (! is_object($persona)) {
            throw new ArcaIntegrationException('El padrón no devolvió la información de la persona.');
        }

        return [
            'condicion_fiscal' => $this->mapper->condicionFiscal($persona),
            'estado' => $this->mapper->estado($persona),
            'nombre' => $this->mapper->nombre($persona) ?: null,
            'domicilio_fiscal' => $this->mapper->domicilioFiscal($persona),
        ];
    }
}
