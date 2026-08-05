<?php

namespace Tests\Feature\Facturacion;

use App\Models\Comercio;
use App\Models\ComprobanteFiscal;
use App\Models\ControlSecuenciaFiscal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCaseMultiTenant;

class ComprobanteFiscalPersistenceTest extends TestCaseMultiTenant
{
    private function crearComprobante(array $overrides = []): ComprobanteFiscal
    {
        return ComprobanteFiscal::create(array_merge([
            'venta_id' => 1,
            'comercio_id' => 1,
            'punto_venta' => 1,
            'tipo' => 'factura',
            'letra' => 'B',
            'numero' => 1,
            'neto' => 100.00,
            'iva' => 21.00,
            'total' => 121.00,
            'estado' => 'emitido',
        ], $overrides));
    }

    public function test_esquema_del_ledger(): void
    {
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'comercio_id'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'venta_id'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'punto_venta'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'tipo'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'letra'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'numero'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'cae'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'vencimiento_cae'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'neto'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'iva'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'total'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'qr'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'comprobante_original_id'));
        $this->assertTrue(Schema::hasColumn('comprobantes_fiscales', 'estado'));
    }

    public function test_crea_comprobante_con_relaciones(): void
    {
        $comprobante = $this->crearComprobante();

        $this->assertInstanceOf(Comercio::class, $comprobante->comercio);
        $this->assertSame(1, $comprobante->comercio->id);
        $this->assertSame(1, $comprobante->venta->id);
        $this->assertSame('0001-00000001', $comprobante->numero_completo);
    }

    public function test_no_admite_duplicados_de_numeracion(): void
    {
        $this->crearComprobante();

        try {
            $this->crearComprobante(['venta_id' => 2]);
            $this->fail('Se esperaba una violación de índice único.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('duplicate', strtolower($e->getMessage()));
        }
    }

    public function test_requiere_comercio_id(): void
    {
        try {
            ComprobanteFiscal::create([
                'venta_id' => 1,
                'punto_venta' => 1,
                'tipo' => 'factura',
                'letra' => 'B',
                'numero' => 99,
                'neto' => 0,
                'iva' => 0,
                'total' => 0,
            ]);
            $this->fail('Se esperaba error por comercio_id nulo.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('null value', strtolower($e->getMessage()));
        }
    }

    public function test_fk_de_venta_debe_existir(): void
    {
        try {
            $this->crearComprobante(['venta_id' => 999999]);
            $this->fail('Se esperaba violación de clave foránea.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key', strtolower($e->getMessage()));
        }
    }

    public function test_nota_credito_referencia_al_original(): void
    {
        $original = $this->crearComprobante();
        $nc = $this->crearComprobante([
            'tipo' => 'nota_credito',
            'letra' => 'B',
            'numero' => 2,
            'comprobante_original_id' => $original->id,
        ]);

        $this->assertSame($original->id, $nc->comprobanteOriginal->id);
        $this->assertCount(1, $original->notasCredito);
    }

    public function test_control_secuencia_reserva_numeros_secuenciales(): void
    {
        $control = ControlSecuenciaFiscal::create([
            'comercio_id' => 1,
            'punto_venta' => 1,
            'tipo' => 'factura',
        ]);

        $this->assertSame(1, $control->reservarProximoNumero());
        $this->assertSame(2, $control->reservarProximoNumero());
    }

    public function test_control_secuencia_no_duplica_la_misma_clave(): void
    {
        ControlSecuenciaFiscal::create([
            'comercio_id' => 1,
            'punto_venta' => 1,
            'tipo' => 'factura',
        ]);

        try {
            ControlSecuenciaFiscal::create([
                'comercio_id' => 1,
                'punto_venta' => 1,
                'tipo' => 'factura',
            ]);
            $this->fail('Se esperaba violación de índice único.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('duplicate', strtolower($e->getMessage()));
        }
    }
}
