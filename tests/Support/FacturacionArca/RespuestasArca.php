<?php

namespace Tests\Support\FacturacionArca;

use DateTimeImmutable;

/**
 * Respuestas SOAP de ARCA simuladas para los tests.
 */
trait RespuestasArca
{
    protected function loginXml(DateTimeImmutable $expiracion): string
    {
        $generado = new DateTimeImmutable;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketResponse version="1.0">
  <header>
    <source>CN=wsaa,OU=wsaa,O=AFIP,C=AR</source>
    <uniqueId>1</uniqueId>
    <generatedAt>{$generado->format('c')}</generatedAt>
    <expirationTime>{$expiracion->format('c')}</expirationTime>
  </header>
  <credentials>
    <token>TOKEN_WSAA_TEST</token>
    <sign>SIGN_WSAA_TEST</sign>
  </credentials>
</loginTicketResponse>
XML;
    }

    protected function respuestaWsfeAprobada(string $cae, string $vencimiento): object
    {
        return (object) [
            'FeCabResp' => (object) [
                'Resultado' => 'A',
                'CantReg' => 1,
                'PtoVta' => 1,
                'CbteTipo' => 6,
            ],
            'FeDetResp' => (object) [
                'FECAEDetResponse' => (object) [
                    'CAE' => $cae,
                    'CAEFchVto' => $vencimiento,
                ],
            ],
        ];
    }

    protected function respuestaWsfeRechazada(string $mensaje): object
    {
        return (object) [
            'FeCabResp' => (object) [
                'Resultado' => 'R',
                'CantReg' => 1,
            ],
            'FeDetResp' => (object) [
                'FECAEDetResponse' => (object) [],
            ],
            'Errors' => (object) [
                'Err' => (object) [
                    'Code' => 10048,
                    'Msg' => $mensaje,
                ],
            ],
        ];
    }

    protected function respuestaPuntoVenta(): object
    {
        return (object) [
            'FEParamGetPtosVentaResult' => (object) [
                'ResultGet' => (object) [
                    'PtoVenta' => [
                        (object) ['Nro' => 1, 'Bloqueado' => 'N'],
                        (object) ['Nro' => 2, 'Bloqueado' => 'S'],
                    ],
                ],
            ],
        ];
    }

    protected function respuestaAlicuotas(): object
    {
        return (object) [
            'FEParamGetTiposIvaResult' => (object) [
                'ResultGet' => (object) [
                    'IvaTipo' => [
                        (object) ['Id' => 5, 'Desc' => 'IVA 21%'],
                        (object) ['Id' => 4, 'Desc' => 'IVA 10.5%'],
                    ],
                ],
            ],
        ];
    }

    protected function personaActivaResponsableInscripto(): object
    {
        return (object) [
            'apellido' => 'PEREZ',
            'nombre' => 'JUAN',
            'estado' => 'ACTIVO',
            'impuesto' => [
                (object) ['descripcionImpuesto' => 'IVA'],
                (object) ['descripcionImpuesto' => 'GANANCIAS'],
            ],
        ];
    }

    protected function personaActivaMonotributo(): object
    {
        return (object) [
            'apellido' => 'GOMEZ',
            'nombre' => 'ANA',
            'estado' => 'ACTIVO',
            'impuesto' => [
                (object) ['descripcionImpuesto' => 'MONOTRIBUTO'],
            ],
        ];
    }
}
