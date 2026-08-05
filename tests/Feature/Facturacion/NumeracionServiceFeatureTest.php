<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Infrastructure\Arca\QrArcaPayloadBuilder;
use App\Facturacion\Infrastructure\Persistence\EloquentComprobanteFiscalRepository;
use App\Models\ControlSecuenciaFiscal;
use Tests\TestCaseMultiTenant;

class NumeracionServiceFeatureTest extends TestCaseMultiTenant
{
    private EloquentComprobanteFiscalRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);
    }

    public function test_es_secuencial_y_sin_huecos(): void
    {
        $a = $this->repositorio->proximoNumero(1, 1, 'factura');
        $b = $this->repositorio->proximoNumero(1, 1, 'factura');
        $c = $this->repositorio->proximoNumero(1, 1, 'factura');

        $this->assertEquals([1, 2, 3], [$a, $b, $c]);

        $control = ControlSecuenciaFiscal::where('comercio_id', 1)
            ->where('punto_venta', 1)
            ->where('tipo', 'factura')
            ->firstOrFail();

        $this->assertSame(3, $control->ultimo_numero);
    }

    public function test_no_retrocede_ante_un_intento_fallido(): void
    {
        $this->repositorio->proximoNumero(1, 1, 'factura');

        $siguiente = $this->repositorio->proximoNumero(1, 1, 'factura');

        $this->assertSame(2, $siguiente);
    }

    public function test_secuencia_independiente_por_comercio(): void
    {
        $comercioA = $this->repositorio->proximoNumero(1, 1, 'factura');
        $comercioB = $this->repositorio->proximoNumero(2, 1, 'factura');

        $this->assertSame(1, $comercioA);
        $this->assertSame(1, $comercioB);
    }

    public function test_secuencia_independiente_por_tipo(): void
    {
        $factura = $this->repositorio->proximoNumero(1, 1, 'factura');
        $notaCredito = $this->repositorio->proximoNumero(1, 1, 'nota_credito');

        $this->assertSame(1, $factura);
        $this->assertSame(1, $notaCredito);
    }
}
