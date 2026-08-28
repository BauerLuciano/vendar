<?php

namespace Tests\Feature\Facturacion;

use App\Models\CertificadoFiscal;
use App\Models\ConfiguracionFiscalComercio;
use Database\Seeders\ConfiguracionFiscalComerciosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCaseMultiTenant;

class ConfiguracionFiscalComercioTest extends TestCaseMultiTenant
{
    public function test_esquema_de_la_configuracion(): void
    {
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'comercio_id'));
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'cuit'));
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'condicion_fiscal'));
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'entorno'));
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'punto_venta_activo'));
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'estado_modulo'));
        $this->assertTrue(Schema::hasColumn('configuracion_fiscal_comercios', 'certificado_id'));
    }

    public function test_seeder_crea_config_por_comercio(): void
    {
        $this->seed(ConfiguracionFiscalComerciosSeeder::class);

        $config = ConfiguracionFiscalComercio::where('comercio_id', 1)->firstOrFail();

        $this->assertSame('sin_datos', $config->estado_modulo);
        $this->assertSame('homologacion', $config->entorno);
        $this->assertCount(2, ConfiguracionFiscalComercio::all());
    }

    public function test_default_de_entorno_en_migracion_es_homologacion_para_no_facturar_en_produccion_por_error(): void
    {
        $migracion = (string) file_get_contents(base_path('database/migrations/2026_08_02_000005_create_configuracion_fiscal_comercios_table.php'));

        $this->assertStringContainsString("->default('homologacion')", $migracion);
    }

    public function test_comercio_id_es_unico(): void
    {
        ConfiguracionFiscalComercio::create(['comercio_id' => 1]);

        try {
            ConfiguracionFiscalComercio::create(['comercio_id' => 1]);
            $this->fail('Se esperaba violación de índice único.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('duplicate', strtolower($e->getMessage()));
        }
    }

    public function test_relaciones_y_accesor(): void
    {
        $config = ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'estado_modulo' => 'listo_para_facturar',
        ]);

        $this->assertTrue($config->lista_para_facturar);
        $this->assertSame(1, $config->comercio->id);
    }

    public function test_relacion_con_certificado(): void
    {
        $certificado = CertificadoFiscal::create([
            'comercio_id' => 1,
            'entorno' => 'produccion',
            'archivo_pfx' => 'encrypted-bytes',
            'password_pfx' => 'encrypted-password',
        ]);

        $config = ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'certificado_id' => $certificado->id,
        ]);

        $this->assertSame($certificado->id, $config->certificado->id);
        $this->assertFalse($certificado->vencido);
    }
}
