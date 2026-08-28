<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\Support\FacturacionDomain\FakeConectividadResolver;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

/**
 * Credencial de plataforma del padrón ARCA (Administración Global) y
 * aislamiento del CUIT global del ticket frente a la verificación fiscal.
 */
class ArcaCredencialTest extends TestCaseMultiTenant
{
    private const CREDENCIAL_CLAVE = 'arca.padron.credencial_plataforma';

    private const CUIT_VALIDO = '20123456786';

    protected function adminGlobal(): User
    {
        $user = User::where('email', 'admin.global@test.com')->first();

        if ($user === null) {
            $user = new User(['name' => 'Admin Global QA', 'email' => 'admin.global@test.com', 'comercio_id' => 1]);
            $user->password = 'secret';
            $user->save();
        }

        $user->assignRole('Administrador Global');

        return $user;
    }

    public function test_index_accesible_para_administrador_global(): void
    {
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.arca.credencial'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminGlobal/ArcaCredencial')
                ->where('configurada', false)
                ->where('cuit', null)
            );
    }

    public function test_index_vedada_para_superadmin(): void
    {
        $this->actingAsAdminA()
            ->get(route('admin.arca.credencial'))
            ->assertForbidden();
    }

    public function test_index_vedada_para_cajero(): void
    {
        $this->actingAsUserA()
            ->get(route('admin.arca.credencial'))
            ->assertForbidden();
    }

    public function test_guardar_credencial_requiere_cuit_token_y_sign(): void
    {
        $this->actingAs($this->adminGlobal())
            ->from(route('admin.arca.credencial'))
            ->post(route('admin.arca.credencial.store'), [
                'cuit' => '20123456789',
                'token' => 'corto',
                'sign' => 'corto',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['cuit', 'token', 'sign']);

        $this->assertDatabaseMissing('configuraciones', ['clave' => self::CREDENCIAL_CLAVE]);
    }

    public function test_guardar_credencial_se_cifra_y_no_expone_el_token(): void
    {
        $this->actingAs($this->adminGlobal())
            ->from(route('admin.arca.credencial'))
            ->post(route('admin.arca.credencial.store'), [
                'cuit' => self::CUIT_VALIDO,
                'token' => 'token-secreto-arca',
                'sign' => 'sign-secreto-arca',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $config = Configuracion::where('clave', self::CREDENCIAL_CLAVE)->firstOrFail();

        $this->assertNotSame('token-secreto-arca', $config->valor);
        $this->assertStringNotContainsString('token-secreto-arca', $config->valor);
        $this->assertStringNotContainsString('sign-secreto-arca', $config->valor);

        $payload = json_decode(Crypt::decryptString($config->valor), true);

        $this->assertSame(self::CUIT_VALIDO, $payload['cuit']);
        $this->assertSame('token-secreto-arca', $payload['token']);
        $this->assertSame('sign-secreto-arca', $payload['sign']);
    }

    public function test_index_muestra_cuit_enmascarado_sin_exponer_secretos(): void
    {
        $this->actingAs($this->adminGlobal())
            ->from(route('admin.arca.credencial'))
            ->post(route('admin.arca.credencial.store'), [
                'cuit' => self::CUIT_VALIDO,
                'token' => 'token-secreto-arca',
                'sign' => 'sign-secreto-arca',
            ]);

        $this->get(route('admin.arca.credencial'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminGlobal/ArcaCredencial')
                ->where('configurada', true)
                ->where('cuit', '20-••••••••-6')
                ->missing('token')
                ->missing('sign')
            );
    }

    public function test_verificar_cuit_no_toca_el_cuit_global_del_ticket(): void
    {
        $padron = new FakePadronConsulta;
        $padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'ACTIVO',
            'nombre' => 'Comercio RI SA',
        ];

        $this->app->instance(PadronResolver::class, new FakePadronResolver($padron));
        $this->app->instance(WsfetResolver::class, new FakeWsfetResolver(new FakeWsfet));
        $this->app->instance(ConectividadResolver::class, new FakeConectividadResolver);

        Configuracion::updateOrCreate(
            ['clave' => 'cuit'],
            ['valor' => '20-00000000-0', 'tipo' => 'texto', 'grupo' => 'general'],
        );

        $this->actingAs($this->adminGlobal())
            ->from(route('configuracion.fiscal.wizard'))
            ->post(route('configuracion.fiscal.cuit'), [
                'cuit' => self::CUIT_VALIDO,
                'entorno' => 'homologacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuraciones', [
            'clave' => 'cuit',
            'valor' => '20-00000000-0',
        ]);
    }
}
