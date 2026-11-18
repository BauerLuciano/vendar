<?php

namespace Tests\Feature;

use App\Enums\PaymentChannel;
use App\Models\PaymentMethodConfiguration;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCaseMultiTenant;

class ConfiguracionMediosPagoTest extends TestCaseMultiTenant
{
    private const MENSAJE_TITULAR = 'Ingresá el nombre del titular.';

    private const MENSAJE_GRUPO = 'Completá al menos uno de Alias, CBU o CVU.';

    private function guardarPmc(array $payload)
    {
        return $this->actingAsAdminA()->post(route('configuracion.metodo-pago.store'), $payload + [
            'enabled' => true,
        ]);
    }

    private function crearPmc(
        int $comercioId,
        string $metodoPago,
        ?string $provider,
        ?array $displayData
    ): PaymentMethodConfiguration {
        return PaymentMethodConfiguration::create([
            'comercio_id' => $comercioId,
            'metodo_pago' => $metodoPago,
            'provider' => $provider,
            'channel' => PaymentChannel::MANUAL,
            'display_data' => $displayData,
            'enabled' => true,
        ]);
    }

    private function assertSinErroresDeTransferencia(): void
    {
        $errors = session('errors');

        $this->assertNotNull($errors);
        $this->assertNotSame(self::MENSAJE_TITULAR, $errors->first('display_data.titular'));
        $this->assertNotSame(self::MENSAJE_GRUPO, $errors->first('display_data'));
    }

    public function test_admin_a_puede_guardar_un_medio_de_pago_valido(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => '',
            'display_data' => [
                'alias' => 'mi.alias',
                'cvu' => '0000003100000000000000',
                'cbu' => '',
                'banco' => 'Banco Nación',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)
            ->where('metodo_pago', 'TRANSFERENCIA')
            ->first();

        $this->assertNotNull($pmc);
        $this->assertSame(PaymentChannel::MANUAL, $pmc->channel);
        $this->assertTrue($pmc->enabled);
        $this->assertSame('mi.alias', $pmc->display_data['alias']);
        $this->assertSame('0000003100000000000000', $pmc->display_data['cvu']);
        $this->assertArrayNotHasKey('cbu', $pmc->display_data);
        $this->assertSame('Banco Nación', $pmc->display_data['banco']);
        $this->assertSame('Juan Pérez', $pmc->display_data['titular']);
    }

    public function test_transferencia_sin_datos_es_rechazada_y_no_persiste(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
            'display_data' => self::MENSAJE_GRUPO,
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_sin_display_data_es_rechazada(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data.array']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_display_data_null_es_rechazada(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data.array']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_solo_con_titular_es_rechazada(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data.titular']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_alias_y_titular_se_guarda(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('mi.alias', $pmc->display_data['alias']);
        $this->assertSame('Juan Pérez', $pmc->display_data['titular']);
        $this->assertArrayNotHasKey('cbu', $pmc->display_data);
        $this->assertArrayNotHasKey('cvu', $pmc->display_data);
        $this->assertArrayNotHasKey('banco', $pmc->display_data);
    }

    public function test_transferencia_con_cbu_y_titular_se_guarda(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'cbu' => '0000000000000000000001',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('0000000000000000000001', $pmc->display_data['cbu']);
        $this->assertArrayNotHasKey('cvu', $pmc->display_data);
    }

    public function test_transferencia_con_cvu_y_titular_se_guarda(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'cvu' => '0000003100000000000001',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('0000003100000000000001', $pmc->display_data['cvu']);
        $this->assertArrayNotHasKey('cbu', $pmc->display_data);
    }

    public function test_transferencia_con_cbu_y_cvu_se_guardan_ambos(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'cbu' => '111',
                'cvu' => '222',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('111', $pmc->display_data['cbu']);
        $this->assertSame('222', $pmc->display_data['cvu']);
    }

    public function test_transferencia_con_todos_los_datos_se_guarda(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'cbu' => '111',
                'cvu' => '222',
                'banco' => 'Banco Nación',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('mi.alias', $pmc->display_data['alias']);
        $this->assertSame('111', $pmc->display_data['cbu']);
        $this->assertSame('222', $pmc->display_data['cvu']);
        $this->assertSame('Banco Nación', $pmc->display_data['banco']);
        $this->assertSame('Juan Pérez', $pmc->display_data['titular']);
    }

    #[DataProvider('titularesFaltantesProvider')]
    public function test_transferencia_con_titular_vacio_solo_espacios_o_null_es_rechazada($titular): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => $titular,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
        ]);
        $response->assertSessionDoesntHaveErrors([
            'display_data.titular.string',
            'display_data.titular.regex',
            'display_data',
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public static function titularesFaltantesProvider(): array
    {
        return [
            'vacío' => [''],
            'sólo espacios' => ['   '],
            'null' => [null],
        ];
    }

    #[DataProvider('gruposFaltantesProvider')]
    public function test_transferencia_con_grupo_vacio_solo_espacios_o_null_es_rechazada(array $grupo): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => $grupo + [
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $response->assertSessionDoesntHaveErrors([
            'display_data.alias',
            'display_data.cbu',
            'display_data.cvu',
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public static function gruposFaltantesProvider(): array
    {
        return [
            'todos vacíos' => [[
                'alias' => '',
                'cbu' => '',
                'cvu' => '',
            ]],
            'todos con espacios' => [[
                'alias' => '   ',
                'cbu' => '   ',
                'cvu' => '   ',
            ]],
            'todos null' => [[
                'alias' => null,
                'cbu' => null,
                'cvu' => null,
            ]],
        ];
    }

    public function test_transferencia_con_banco_y_titular_sin_cuenta_es_rechazada(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'banco' => 'Banco Nación',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => self::MENSAJE_GRUPO,
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_alias_y_cbu_no_numerico_devuelve_solo_error_de_cbu(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'cbu' => 'abc',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.cbu' => 'El CBU solo puede contener números.',
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data', 'display_data.titular']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_alias_y_cvu_no_numerico_devuelve_solo_error_de_cvu(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'cvu' => 'abc',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.cvu' => 'El CVU solo puede contener números.',
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data', 'display_data.titular']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_alias_y_banco_invalido_devuelve_solo_error_de_banco(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'banco' => '123',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.banco' => 'El banco solo puede contener letras.',
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data', 'display_data.titular']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_alias_y_titular_invalido_devuelve_solo_error_de_titular(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => '123',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => 'El nombre del titular solo puede contener letras.',
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_con_campos_de_cuenta_solo_espacios_es_rechazada_sin_errores_de_formato(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => '   ',
                'cbu' => '   ',
                'cvu' => '   ',
                'banco' => '   ',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $response->assertSessionDoesntHaveErrors([
            'display_data.alias',
            'display_data.cbu',
            'display_data.cvu',
            'display_data.banco',
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_transferencia_elimina_campos_opcionales_vacios_y_conserva_validos(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => '   ',
                'cvu' => '123',
                'cbu' => '',
                'banco' => '   ',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('123', $pmc->display_data['cvu']);
        $this->assertSame('Juan Pérez', $pmc->display_data['titular']);
        $this->assertArrayNotHasKey('alias', $pmc->display_data);
        $this->assertArrayNotHasKey('cbu', $pmc->display_data);
        $this->assertArrayNotHasKey('banco', $pmc->display_data);
    }

    public function test_transferencia_con_solo_claves_desconocidas_es_rechazada(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'foo' => 'bar',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
            'display_data' => self::MENSAJE_GRUPO,
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_actualizacion_valida_conserva_misma_fila(): void
    {
        $pmc = $this->crearPmc(1, 'TRANSFERENCIA', 'transfer-test', [
            'alias' => 'alias.viejo',
            'titular' => 'Juan Pérez',
        ]);

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [
                'alias' => 'alias.nuevo',
                'cvu' => '123',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_method_configurations', 1);

        $pmc->refresh();
        $this->assertSame(1, $pmc->comercio_id);
        $this->assertSame('alias.nuevo', $pmc->display_data['alias']);
        $this->assertSame('123', $pmc->display_data['cvu']);
    }

    public function test_actualizacion_invalida_conserva_display_data_original(): void
    {
        $pmc = $this->crearPmc(1, 'TRANSFERENCIA', 'transfer-test', [
            'alias' => 'alias.viejo',
            'titular' => 'Juan Pérez',
        ]);
        $original = $pmc->display_data;

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 1);

        $pmc->refresh();
        $this->assertSame($original, $pmc->display_data);
    }

    public function test_configuracion_incompleta_no_puede_actualizarse_hasta_completar_datos(): void
    {
        $pmc = $this->crearPmc(1, 'TRANSFERENCIA', 'transfer-test', [
            'titular' => 'Juan Pérez',
        ]);
        $original = $pmc->display_data;

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 1);
        $pmc->refresh();
        $this->assertSame($original, $pmc->display_data);

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [
                'alias' => 'juan.alias',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_method_configurations', 1);

        $pmc->refresh();
        $this->assertSame('juan.alias', $pmc->display_data['alias']);
        $this->assertSame('Juan Pérez', $pmc->display_data['titular']);
    }

    public function test_actualizacion_cbu_a_cvu_elimina_cbu(): void
    {
        $pmc = $this->crearPmc(1, 'TRANSFERENCIA', 'transfer-test', [
            'cbu' => '111',
            'titular' => 'Juan Pérez',
        ]);

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [
                'cbu' => '',
                'cvu' => '222',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc->refresh();
        $this->assertSame('222', $pmc->display_data['cvu']);
        $this->assertArrayNotHasKey('cbu', $pmc->display_data);
    }

    public function test_actualizacion_cvu_a_cbu_elimina_cvu(): void
    {
        $pmc = $this->crearPmc(1, 'TRANSFERENCIA', 'transfer-test', [
            'cvu' => '222',
            'titular' => 'Juan Pérez',
        ]);

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [
                'cbu' => '111',
                'cvu' => '',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc->refresh();
        $this->assertSame('111', $pmc->display_data['cbu']);
        $this->assertArrayNotHasKey('cvu', $pmc->display_data);
    }

    public function test_actualizacion_conserva_cbu_y_cvu_simultaneos(): void
    {
        $pmc = $this->crearPmc(1, 'TRANSFERENCIA', 'transfer-test', [
            'cbu' => '111',
            'cvu' => '222',
            'titular' => 'Juan Pérez',
        ]);

        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-test',
            'display_data' => [
                'cbu' => '333',
                'cvu' => '444',
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc->refresh();
        $this->assertSame('333', $pmc->display_data['cbu']);
        $this->assertSame('444', $pmc->display_data['cvu']);
    }

    #[DataProvider('otrosMetodosDisplayDataProvider')]
    public function test_otros_metodos_con_display_data_vacio_ausente_o_null_se_guardan(string $metodo, $displayData, bool $incluirDisplayData): void
    {
        $payload = [
            'metodo_pago' => $metodo,
        ];

        if ($incluirDisplayData) {
            $payload['display_data'] = $displayData;
        }

        $response = $this->guardarPmc($payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_method_configurations', 1);
    }

    public static function otrosMetodosDisplayDataProvider(): array
    {
        $metodos = ['EFECTIVO', 'DEBITO', 'CREDITO', 'MERCADO_PAGO', 'CUENTA_CORRIENTE', 'VIUMI'];
        $casos = [];

        foreach ($metodos as $metodo) {
            $casos[$metodo.' vacío'] = [$metodo, [], true];
            $casos[$metodo.' null'] = [$metodo, null, true];
            $casos[$metodo.' ausente'] = [$metodo, null, false];
        }

        return $casos;
    }

    public function test_otros_metodos_con_cadenas_de_espacios_se_conservan(): void
    {
        // El middleware global de Laravel recorta y convierte los espacios en
        // null antes del controlador. Se desactiva sólo aquí para verificar
        // que ConfiguracionController no normaliza los demás métodos.
        $this->withoutMiddleware([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        $response = $this->guardarPmc([
            'metodo_pago' => 'EFECTIVO',
            'display_data' => [
                'alias' => '   ',
                'banco' => '   ',
                'titular' => '   ',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('   ', $pmc->display_data['alias']);
        $this->assertSame('   ', $pmc->display_data['banco']);
        $this->assertSame('   ', $pmc->display_data['titular']);
    }

    public function test_otros_metodos_con_cbu_o_cvu_de_espacios_no_agregan_errores_de_transferencia(): void
    {
        // Ver comentario del test anterior: se aísla el middleware global para
        // comprobar el comportamiento propio del controlador.
        $this->withoutMiddleware([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        $response = $this->guardarPmc([
            'metodo_pago' => 'EFECTIVO',
            'display_data' => [
                'cbu' => '   ',
                'cvu' => '   ',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionDoesntHaveErrors(['display_data', 'display_data.titular']);

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('   ', $pmc->display_data['cbu']);
        $this->assertSame('   ', $pmc->display_data['cvu']);
    }

    public function test_otro_metodo_con_cvu_invalido_no_agrega_errores_de_transferencia(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'EFECTIVO',
            'display_data' => [
                'cvu' => 'abc',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.cvu' => 'El CVU solo puede contener números.',
        ]);
        $this->assertSinErroresDeTransferencia();

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_display_data_string_en_transferencia_solo_devuelve_error_de_formato(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => 'no-es-un-array',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => 'Los datos para mostrar no tienen un formato válido.',
        ]);
        $response->assertSessionDoesntHaveErrors(['display_data.titular']);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_metodo_ausente_no_agrega_errores_de_transferencia(): void
    {
        $response = $this->guardarPmc([
            'display_data' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'metodo_pago' => 'Seleccioná un método de pago.',
        ]);
        $this->assertSinErroresDeTransferencia();

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_metodo_invalido_no_agrega_errores_de_transferencia(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'NO_EXISTE',
            'display_data' => [
                'cvu' => 'no-numerico',
                'cbu' => 'no-numerico',
                'banco' => '123',
                'titular' => '123',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'metodo_pago' => 'Seleccioná un método de pago válido.',
            'display_data.cvu' => 'El CVU solo puede contener números.',
            'display_data.cbu' => 'El CBU solo puede contener números.',
            'display_data.banco' => 'El banco solo puede contener letras.',
            'display_data.titular' => 'El nombre del titular solo puede contener letras.',
        ]);
        $this->assertSinErroresDeTransferencia();

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_cajero_no_puede_guardar_medios_de_pago(): void
    {
        $response = $this->actingAsUserA()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => 'Juan Pérez',
            ],
            'enabled' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_titular_faltante_devuelve_mensaje_exacto(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_grupo_faltante_devuelve_mensaje_exacto(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_ambos_faltantes_devuelven_ambos_mensajes_exactos(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
            'display_data' => self::MENSAJE_GRUPO,
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_titular_null_no_acumula_mensajes(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => null,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => self::MENSAJE_TITULAR,
        ]);
        $response->assertSessionDoesntHaveErrors([
            'display_data.titular.string',
            'display_data.titular.regex',
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_claves_desconocidas_se_preservan(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => 'Juan Pérez',
                'foo' => 'bar',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('bar', $pmc->display_data['foo']);
    }

    public function test_valores_con_contenido_se_guardan_exactos(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'cbu' => '0000000000000000000001',
                'cvu' => '0000003100000000000001',
                'banco' => 'Banco Nación',
                'titular' => 'Juan Pérez, Jr.',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('mi.alias', $pmc->display_data['alias']);
        $this->assertSame('0000000000000000000001', $pmc->display_data['cbu']);
        $this->assertSame('0000003100000000000001', $pmc->display_data['cvu']);
        $this->assertSame('Banco Nación', $pmc->display_data['banco']);
        $this->assertSame('Juan Pérez, Jr.', $pmc->display_data['titular']);
    }

    public function test_cero_se_considera_informado(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => '0',
                'cbu' => '0',
                'cvu' => '0',
                'titular' => 'Titular',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)->firstOrFail();
        $this->assertSame('0', $pmc->display_data['alias']);
        $this->assertSame('0', $pmc->display_data['cbu']);
        $this->assertSame('0', $pmc->display_data['cvu']);
    }

    public function test_alias_de_255_caracteres_se_acepta(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => str_repeat('a', 255),
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_method_configurations', 1);
    }

    public function test_alias_de_256_caracteres_devuelve_error_de_maximo(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => str_repeat('a', 256),
                'titular' => 'Juan Pérez',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.alias' => 'El alias no debe superar los 255 caracteres.',
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_titular_de_256_caracteres_devuelve_error_de_maximo(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => str_repeat('a', 256),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data.titular' => 'El nombre del titular no debe superar los 255 caracteres.',
        ]);
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_titular_con_comas_y_puntos_es_valido(): void
    {
        $response = $this->guardarPmc([
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [
                'alias' => 'mi.alias',
                'titular' => 'Juan Pérez, Jr.',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_method_configurations', 1);
    }
}
