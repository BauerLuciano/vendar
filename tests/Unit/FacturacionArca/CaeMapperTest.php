<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Wsfe\CaeMapper;
use PHPUnit\Framework\TestCase;

class CaeMapperTest extends TestCase
{
    private CaeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new CaeMapper;
    }

    private function respuesta(string $resultado, ?string $cae = null, ?string $vto = null): object
    {
        return (object) [
            'FeCabResp' => (object) ['Resultado' => $resultado],
            'FeDetResp' => (object) [
                'FECAEDetResponse' => (object) ['CAE' => $cae, 'CAEFchVto' => $vto],
            ],
        ];
    }

    public function test_resultado_a_devuelve_cae(): void
    {
        $cae = $this->mapper->desdeRespuesta($this->respuesta('A', '64001234567890', '20260813'));

        $this->assertNotNull($cae);
        $this->assertSame('64001234567890', $cae->codigo());
        $this->assertSame('2026-08-13', $cae->vencimiento()->format('Y-m-d'));
    }

    public function test_resultado_rechazo_devuelve_null(): void
    {
        $this->assertNull($this->mapper->desdeRespuesta($this->respuesta('R')));
    }

    public function test_cae_invalido_lanza_error_de_integracion(): void
    {
        $this->expectException(ArcaIntegrationException::class);

        $this->mapper->desdeRespuesta($this->respuesta('A', '123', '20260813'));
    }

    public function test_detalle_arreglado_usa_el_primero(): void
    {
        $respuesta = (object) [
            'FeCabResp' => (object) ['Resultado' => 'A'],
            'FeDetResp' => (object) [
                'FECAEDetResponse' => [
                    (object) ['CAE' => '11111111111111', 'CAEFchVto' => '20260813'],
                ],
            ],
        ];

        $cae = $this->mapper->desdeRespuesta($respuesta);

        $this->assertSame('11111111111111', $cae?->codigo());
    }

    public function test_errores_devuelve_mensajes(): void
    {
        $respuesta = (object) [
            'Errors' => (object) [
                'Err' => (object) [
                    'Code' => 10048,
                    'Msg' => 'El comprobante no se puede facturar',
                ],
            ],
        ];

        $this->assertSame(['El comprobante no se puede facturar'], $this->mapper->errores($respuesta));
    }

    public function test_errores_devuelve_lista_vacia_sin_errores(): void
    {
        $this->assertSame([], $this->mapper->errores((object) []));
    }
}
