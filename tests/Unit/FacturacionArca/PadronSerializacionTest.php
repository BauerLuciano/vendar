<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Cifrado\PlataformaCredential;
use SoapClient;
use SoapVar;
use Tests\TestCase;

/**
 * Protege contra la regresión de serialización de getPersona_v2: el request
 * SOAP debe enviar token/sign/cuitRepresentada/idPersona como un único
 * argumento estructurado (SoapVar SOAP_ENC_OBJECT) y NUNCA como argumentos
 * posicionales param1/param2. FakeArcaSoapTransport no serializa XML, por lo
 * que esta prueba usa un SoapClient real contra un WSDL local offline.
 */
class PadronSerializacionTest extends TestCase
{
    public function test_getPersona_v2_se_serializa_con_parametros_nombrados(): void
    {
        $cuit = '20403407888';

        $params = (new PlataformaCredential(
            new Cuit($cuit),
            'TOKEN_PLATAFORMA',
            'SIGN_PLATAFORMA',
        ))->parametrosConsulta(new Cuit($cuit));

        $cliente = new SoapClient(__DIR__.'/Fixtures/padron_personaServiceA5.wsdl', [
            'trace' => true,
            'exceptions' => false,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 2,
        ]);

        // Misma forma de argumento que PadronClient::consultar().
        $cliente->__soapCall('getPersona_v2', [new SoapVar($params, SOAP_ENC_OBJECT)]);

        $request = $cliente->__getLastRequest();

        $this->assertStringContainsString('getPersona_v2', $request, 'El request debe invocar getPersona_v2.');
        $this->assertTrue(
            $this->contieneElemento($request, 'token', 'TOKEN_PLATAFORMA'),
            'El request debe incluir el elemento token.'
        );
        $this->assertTrue(
            $this->contieneElemento($request, 'sign', 'SIGN_PLATAFORMA'),
            'El request debe incluir el elemento sign.'
        );
        $this->assertTrue(
            $this->contieneElemento($request, 'cuitRepresentada', $cuit),
            'El request debe incluir el elemento cuitRepresentada.'
        );
        $this->assertTrue(
            $this->contieneElemento($request, 'idPersona', $cuit),
            'El request debe incluir el elemento idPersona.'
        );
        $this->assertStringNotContainsString('<param1>', $request, 'No debe serializar como argumentos posicionales param1.');
        $this->assertStringNotContainsString('<param2>', $request, 'No debe serializar como argumentos posicionales param2.');
    }

    private function contieneElemento(string $xml, string $nombre, string $valor): bool
    {
        return (bool) preg_match(
            '/<(?:[a-z0-9_]+:)?'.preg_quote($nombre, '/').'[^>]*>'.preg_quote($valor, '/').'<\\/[^>]*>/i',
            $xml
        );
    }
}
