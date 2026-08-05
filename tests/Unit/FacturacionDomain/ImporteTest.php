<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Exceptions\ImporteInvalidoException;
use App\Facturacion\Domain\ValueObjects\Importe;
use PHPUnit\Framework\TestCase;

class ImporteTest extends TestCase
{
    public function test_redondea_a_2_decimales(): void
    {
        $this->assertEquals(10.00, (new Importe(10.004))->valor());
        $this->assertEquals(10.01, (new Importe(10.005))->valor());
        $this->assertEquals(1234.57, (new Importe(1234.567))->valor());
    }

    public function test_sumar(): void
    {
        $resultado = (new Importe(10.5))->sumar(new Importe(2.25));
        $this->assertEquals(12.75, $resultado->valor());
    }

    public function test_restar(): void
    {
        $resultado = (new Importe(10.5))->restar(new Importe(2.25));
        $this->assertEquals(8.25, $resultado->valor());
    }

    public function test_multiplicar_por(): void
    {
        $resultado = (new Importe(10.5))->multiplicarPor(2.0);
        $this->assertEquals(21.0, $resultado->valor());
    }

    public function test_es_cero(): void
    {
        $this->assertTrue(Importe::cero()->esCero());
        $this->assertFalse((new Importe(1.0))->esCero());
    }

    public function test_comparaciones(): void
    {
        $a = new Importe(10.0);
        $b = new Importe(5.0);

        $this->assertTrue($a->esMayorQue($b));
        $this->assertTrue($b->esMenorOIgualQue($a));
        $this->assertTrue($a->esMayorOIgualQue($b));
        $this->assertTrue((new Importe(10.0))->esIgual(new Importe(10.0)));
    }

    public function test_rechaza_infinito(): void
    {
        $this->expectException(ImporteInvalidoException::class);
        new Importe(INF);
    }
}
