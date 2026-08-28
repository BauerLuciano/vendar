<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Models\CertificadoFiscal;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\Support\FacturacionDomain\FakeConectividadResolver;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

class F7_WizardConfiguracionFiscalTest extends TestCaseMultiTenant
{
    private FakeWsfet $wsfet;

    private FakePadronConsulta $padron;

    private FakeConectividadResolver $conectividad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wsfet = new FakeWsfet;
        $this->padron = new FakePadronConsulta;
        $this->conectividad = new FakeConectividadResolver;

        $this->wsfet->puntosVenta = [
            ['nro' => 1, 'bloqueado' => false],
            ['nro' => 2, 'bloqueado' => true],
        ];

        $this->app->instance(WsfetResolver::class, new FakeWsfetResolver($this->wsfet));
        $this->app->instance(PadronResolver::class, new FakePadronResolver($this->padron));
        $this->app->instance(ConectividadResolver::class, $this->conectividad);
    }

    public function test_wizard_muestra_estado_inicial_sin_datos(): void
    {
        $this->actingAsAdminA()
            ->get(route('configuracion.fiscal.wizard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturacion/Wizard')
                ->where('configuracion', null)
                ->where('certificado', null)
            );
    }

    public function test_verificar_cuit_ri_avanza_a_datos_cargados_en_homologacion(): void
    {
        $this->padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'ACTIVO',
            'nombre' => 'Comercio RI SA',
            'domicilio_fiscal' => 'ERNESTO CASTELLANO 7, VILLA DOLORES, CORDOBA, 5870',
        ];

        $this->actingAsAdminGlobal()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'cuit' => GeneraPfx::CUIT_VALIDO,
            'razon_social' => 'Comercio RI SA',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'ERNESTO CASTELLANO 7, VILLA DOLORES, CORDOBA, 5870',
            'entorno' => 'homologacion',
            'estado_modulo' => 'datos_cargados',
        ]);
    }

    public function test_verificar_cuit_sin_domicilio_deja_el_campo_vacio(): void
    {
        $this->padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'ACTIVO',
            'nombre' => 'Comercio RI SA',
            'domicilio_fiscal' => null,
        ];

        $this->actingAsAdminGlobal()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'cuit' => GeneraPfx::CUIT_VALIDO,
            'domicilio_fiscal' => null,
            'entorno' => 'homologacion',
            'estado_modulo' => 'datos_cargados',
        ]);
    }

    public function test_verificar_cuit_produccion_vedado_para_comercio_nuevo(): void
    {
        $this->padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'ACTIVO',
            'nombre' => 'Comercio RI SA',
        ];

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'produccion',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cuit');

        $this->assertDatabaseMissing('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'entorno' => 'produccion',
        ]);
    }

    public function test_verificar_cuit_puede_pasar_a_produccion_tras_homologacion(): void
    {
        $this->padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'ACTIVO',
            'nombre' => 'Comercio RI SA',
        ];

        $this->actingAsAdminGlobal()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'produccion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'entorno' => 'produccion',
            'estado_modulo' => 'datos_cargados',
        ]);
    }

    public function test_verificar_cuit_monotributo_queda_no_soportado(): void
    {
        $this->padron->respuesta = [
            'condicion_fiscal' => 'monotributo',
            'estado' => 'ACTIVO',
            'nombre' => 'Monotributista',
        ];

        $this->actingAsAdminGlobal()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cuit');

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'estado_modulo' => 'no_soportado',
        ]);
    }

    public function test_verificar_cuit_no_activo_queda_cuit_inactivo(): void
    {
        $this->padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'BAJA',
            'nombre' => 'Dado de baja',
        ];

        $this->actingAsAdminGlobal()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cuit');

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'estado_modulo' => 'cuit_inactivo',
        ]);
    }

    public function test_homologacion_permitida_para_superadmin(): void
    {
        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'entorno' => 'homologacion',
            'estado_modulo' => 'datos_cargados',
        ]);
    }

    public function test_homologacion_permitida_para_administrador_global(): void
    {
        $adminGlobal = new User([
            'name' => 'Admin Global QA',
            'email' => 'admin.global@test.com',
            'comercio_id' => 1,
        ]);
        $adminGlobal->password = 'secret';
        $adminGlobal->save();
        $adminGlobal->assignRole('Administrador Global');

        $this->actingAs($adminGlobal)
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => GeneraPfx::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'entorno' => 'homologacion',
            'estado_modulo' => 'datos_cargados',
        ]);
    }

    private function actingAsAdminGlobal(): self
    {
        $adminGlobal = new User([
            'name' => 'Admin Global QA',
            'email' => 'admin.global.qa@test.com',
            'comercio_id' => 1,
        ]);
        $adminGlobal->password = 'secret';
        $adminGlobal->save();
        $adminGlobal->assignRole('Administrador Global');

        return $this->actingAs($adminGlobal);
    }

    public function test_confirmar_datos_avanza_a_datos_validados(): void
    {
        $this->configuracion('datos_cargados');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.datos'), [
                'domicilio_fiscal' => 'Av. Siempre Viva 742',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'domicilio_fiscal' => 'Av. Siempre Viva 742',
            'estado_modulo' => 'datos_validados',
        ]);
    }

    public function test_cargar_certificado_encriptado_avanza_a_cert_cargado(): void
    {
        $this->configuracion('datos_validados');

        $certificado = GeneraPfx::valido('clave-secreta');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.certificado'), [
                'archivo_pfx' => UploadedFile::fake()->createWithContent('cert.pfx', $certificado['pfx']),
                'password_pfx' => $certificado['password'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $modelo = CertificadoFiscal::where('comercio_id', 1)->firstOrFail();

        $this->assertNotSame($certificado['pfx'], $modelo->archivo_pfx);
        $this->assertNotSame($certificado['password'], $modelo->password_pfx);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $modelo->archivo_pfx);

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'certificado_id' => $modelo->id,
            'estado_modulo' => 'cert_cargado',
        ]);
    }

    public function test_cargar_certificado_vencido_queda_certificado_vencido(): void
    {
        $this->configuracion('datos_validados');

        $vencido = GeneraPfx::vencido('clave-secreta');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.certificado'), [
                'archivo_pfx' => UploadedFile::fake()->createWithContent('cert.pfx', $vencido['pfx']),
                'password_pfx' => $vencido['password'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('certificado');

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'estado_modulo' => 'certificado_vencido',
        ]);
    }

    public function test_seleccionar_punto_de_venta_avanza_a_pv_habilitado(): void
    {
        $this->configuracion('cert_cargado');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.punto-venta'), [
                'punto_venta' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'punto_venta_activo' => 2,
            'estado_modulo' => 'pv_habilitado',
        ]);
    }

    public function test_probar_conexion_ejecuta_la_suite(): void
    {
        $this->configuracion('pv_habilitado');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.probar-conexion'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->conectividad->llamadas);
    }

    public function test_activar_avanza_a_listo_para_facturar(): void
    {
        $this->configuracion('pv_habilitado');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.activar'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'estado_modulo' => 'listo_para_facturar',
        ]);
    }

    public function test_activar_sin_punto_de_venta_rechaza(): void
    {
        $this->configuracion('cert_cargado');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.activar'))
            ->assertRedirect()
            ->assertSessionHasErrors('activar');

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'estado_modulo' => 'cert_cargado',
        ]);
    }

    public function test_activar_queda_bloqueado_en_no_soportado(): void
    {
        $this->configuracion('no_soportado');

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.activar'))
            ->assertRedirect()
            ->assertSessionHasErrors('activar');

        $this->assertDatabaseHas('configuracion_fiscal_comercios', [
            'comercio_id' => 1,
            'estado_modulo' => 'no_soportado',
        ]);
    }

    private function configuracion(string $estado): void
    {
        $conPuntoVenta = in_array($estado, ['pv_habilitado', 'listo_para_facturar']);
        $conCertificado = in_array($estado, ['cert_cargado', 'pv_habilitado', 'listo_para_facturar']);

        $certificadoId = null;

        if ($conCertificado) {
            $certificado = CertificadoFiscal::create([
                'comercio_id' => 1,
                'entorno' => 'produccion',
                'archivo_pfx' => 'datos-encriptados',
                'password_pfx' => 'encriptado',
                'distinguished_name' => '/CN=CUIT '.GeneraPfx::CUIT_VALIDO,
                'numero_serie' => '1234',
                'vigencia_desde' => now()->subDay(),
                'vigencia_hasta' => now()->addYear(),
            ]);
            $certificadoId = $certificado->id;
        }

        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => GeneraPfx::CUIT_VALIDO,
            'razon_social' => 'Comercio RI SA',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 1',
            'entorno' => 'produccion',
            'punto_venta_activo' => $conPuntoVenta ? 1 : null,
            'certificado_id' => $certificadoId,
            'estado_modulo' => $estado,
        ]);
    }
}
