<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Infrastructure\Arca\Padron\CondicionFiscalMapper;
use PHPUnit\Framework\TestCase;
use Tests\Support\FacturacionArca\RespuestasArca;

class CondicionFiscalMapperTest extends TestCase
{
    use RespuestasArca;

    private CondicionFiscalMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new CondicionFiscalMapper;
    }

    public function test_persona_con_iva_es_responsable_inscripto(): void
    {
        $this->assertSame(
            CondicionFiscal::RESPONSABLE_INSCRIPTO->value,
            $this->mapper->condicionFiscal($this->personaActivaResponsableInscripto())
        );
    }

    public function test_persona_monotributo(): void
    {
        $this->assertSame(
            CondicionFiscal::MONOTRIBUTO->value,
            $this->mapper->condicionFiscal($this->personaActivaMonotributo())
        );
    }

    public function test_persona_sin_impuestos_es_consumidor_final(): void
    {
        $persona = (object) ['apellido' => 'LOPEZ', 'nombre' => 'MARIA', 'estado' => 'ACTIVO'];

        $this->assertSame(
            CondicionFiscal::CONSUMIDOR_FINAL->value,
            $this->mapper->condicionFiscal($persona)
        );
    }

    public function test_impuesto_unico_normaliza_a_arreglo(): void
    {
        $persona = (object) [
            'estado' => 'ACTIVO',
            'impuesto' => (object) ['descripcionImpuesto' => 'IVA'],
        ];

        $this->assertSame(
            CondicionFiscal::RESPONSABLE_INSCRIPTO->value,
            $this->mapper->condicionFiscal($persona)
        );
    }

    public function test_esquema_get_persona_v2_anidado(): void
    {
        $persona = $this->personaActivaResponsableInscripto();

        $this->assertSame('ACTIVO', $this->mapper->estado($persona));
        $this->assertSame('PEREZ JUAN', $this->mapper->nombre($persona));
        $this->assertSame(
            CondicionFiscal::RESPONSABLE_INSCRIPTO->value,
            $this->mapper->condicionFiscal($persona)
        );
    }

    public function test_estado_y_nombre(): void
    {
        $persona = (object) ['apellido' => 'PEREZ', 'nombre' => 'JUAN', 'estado' => 'inactivo'];

        $this->assertSame('INACTIVO', $this->mapper->estado($persona));
        $this->assertSame('PEREZ JUAN', $this->mapper->nombre($persona));
    }
}
