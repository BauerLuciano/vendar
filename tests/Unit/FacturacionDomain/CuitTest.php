<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Exceptions\CuitInvalidoException;
use App\Facturacion\Domain\ValueObjects\Cuit;
use PHPUnit\Framework\TestCase;

class CuitTest extends TestCase
{
    public function test_valida_digito_verificador_correctamente(): void
    {
        $this->assertTrue(Cuit::esValido('20123456786'));
        $this->assertTrue(Cuit::esValido('30500010912'));
    }

    public function test_acepta_formatos_con_guiones_y_puntos(): void
    {
        $cuit = new Cuit('20-12345678-6');
        $this->assertEquals('20123456786', $cuit->valor());

        $cuitPuntos = new Cuit('20.12345678.6');
        $this->assertEquals('20123456786', $cuitPuntos->valor());
    }

    public function test_normaliza_pegado_con_espacios_y_mezcla_de_caracteres(): void
    {
        $cuit = new Cuit('20 12345678 6');
        $this->assertEquals('20123456786', $cuit->valor());

        $cuitMezclado = new Cuit(' 20 - 1234 5678-6 ');
        $this->assertEquals('20123456786', $cuitMezclado->valor());
        $this->assertTrue(Cuit::esValido('20 12345678 6'));
    }

    public function test_rechaza_cuit_con_mas_de_11_digitos(): void
    {
        $this->assertFalse(Cuit::esValido('201234567869'));
    }

    public function test_formatea_cuit(): void
    {
        $cuit = new Cuit('20123456786');
        $this->assertEquals('20-12345678-6', $cuit->formateado());
    }

    public function test_rechaza_digito_verificador_incorrecto(): void
    {
        $this->assertFalse(Cuit::esValido('20123456785'));
    }

    public function test_rechaza_longitud_incorrecta(): void
    {
        $this->assertFalse(Cuit::esValido('2012345678'));
        $this->assertFalse(Cuit::esValido('201234567861'));
        $this->assertFalse(Cuit::esValido(''));
    }

    public function test_rechaza_caracteres_no_numericos(): void
    {
        $this->assertFalse(Cuit::esValido('2012345678A'));
        $this->assertFalse(Cuit::esValido('abcdefghijk'));
    }

    public function test_constructor_lanza_excepcion_para_cuit_invalido(): void
    {
        $this->expectException(CuitInvalidoException::class);
        new Cuit('20123456785');
    }

    public function test_es_igual_por_valor(): void
    {
        $a = new Cuit('20123456786');
        $b = new Cuit('20-12345678-6');
        $c = new Cuit('30500010912');

        $this->assertTrue($a->esIgual($b));
        $this->assertFalse($a->esIgual($c));
    }
}
