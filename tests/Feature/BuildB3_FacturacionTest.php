<?php

namespace Tests\Feature;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Exceptions\PagoManualDeshabilitadoException;
use App\Exceptions\PagoManualInvalidoException;
use App\Models\Comercio;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\Suscripcion\PagoManualService;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCaseMultiTenant;

class BuildB3_FacturacionTest extends TestCaseMultiTenant
{
    private User $globalAdminB3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalAdminB3 = new User();
        $this->globalAdminB3->name = 'Administrador Global B3';
        $this->globalAdminB3->email = 'global.b3@test.com';
        $this->globalAdminB3->password = 'password';
        $this->globalAdminB3->is_active = true;
        $this->globalAdminB3->save();
        $this->globalAdminB3->assignRole('Administrador Global');
    }

    // ============================================================
    // 1-3. PLAN Y PRECIOS PENDIENTES
    // ============================================================

    public function test_la_relacion_del_plan_obtiene_el_nombre(): void
    {
        $comercio = Comercio::findOrFail(1);

        $this->assertSame(1, (int) $comercio->plan_id);
        $this->assertSame('Plan Básico', $comercio->planContratado?->nombre);
        $this->assertSame(8000.0, (float) $comercio->planContratado?->precio_mensual);
    }

    public function test_la_informacion_del_plan_no_se_confunde_con_la_columna_plan(): void
    {
        $comercio = Comercio::findOrFail(1);

        // La columna legacy dice 'basico' pero el plan contratado es el ID 2.
        $comercio->plan = 'basico';
        $comercio->plan_id = 2;
        $comercio->save();
        $comercio->refresh();

        $this->assertSame('basico', $comercio->plan);
        $this->assertSame('Plan Profesional', $comercio->planContratado?->nombre);
        $this->assertNotSame($comercio->plan, $comercio->planContratado?->nombre);
    }

    public function test_los_precios_configurados_no_se_presentan_como_confirmados(): void
    {
        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AdminGlobal/Facturacion')
            ->where('precios_confirmados', false)
            ->where('cobros_habilitados', false)
        );

        $props = $response->viewData('page')['props'];

        $this->assertStringContainsString('pendientes de definición', $props['aviso_precios']);
        $this->assertArrayNotHasKey('total_esperado', $props['resumen']);
        $this->assertArrayNotHasKey('total_vencido', $props['resumen']);
        $this->assertTrue($props['comercios'][0]['precio_es_referencia']);
    }

    // ============================================================
    // 4-6. ESTADOS DE FACTURACIÓN Y MORA
    // ============================================================

    public function test_los_estados_de_facturacion_respetan_las_fechas(): void
    {
        Comercio::whereKey(1)->update([
            'vencimiento_pago' => now()->addDays(5)->toDateString(),
        ]);
        Comercio::whereKey(2)->update([
            'vencimiento_pago' => now()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');
        $response->assertOk();

        $comercios = collect($response->viewData('page')['props']['comercios'])
            ->keyBy('id');

        $this->assertSame('Por Vencer', $comercios[1]['estado_cobro']);
        $this->assertSame(5, $comercios[1]['dias_restantes']);
        $this->assertSame('Vencido', $comercios[2]['estado_cobro']);
        $this->assertSame(3, $comercios[2]['dias_atraso']);
    }

    public function test_el_dia_del_vencimiento_no_figura_vencido_antes_de_finalizar_la_jornada(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = now()->toDateString();
        $comercio->save();

        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');
        $response->assertOk();

        $comercios = collect($response->viewData('page')['props']['comercios'])
            ->keyBy('id');

        $this->assertSame('Al Día', $comercios[1]['estado_cobro']);
        $this->assertSame(0, $comercios[1]['dias_restantes']);
        $this->assertNotSame('Vencido', $comercios[1]['estado_cobro']);
    }

    public function test_los_comercios_suspendidos_aparecen_en_metricas_y_listados_de_mora(): void
    {
        $comercio = Comercio::findOrFail(2);
        $comercio->status = 'suspendido';
        $comercio->vencimiento_pago = now()->subDays(10)->toDateString();
        $comercio->save();

        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $comercios = collect($props['comercios'])->keyBy('id');

        $this->assertSame('Suspendido', $comercios[2]['estado_cobro']);
        $this->assertSame(1, $props['resumen']['suspendidos']);
        $this->assertSame(1, $props['resumen']['en_mora']);
        $this->assertSame(1, $props['resumen']['clientes_morosos']);
    }

    // ============================================================
    // 7-9. PAGOS DESHABILITADOS Y REACTIVACIÓN
    // ============================================================

    public function test_el_registro_de_pagos_reales_esta_deshabilitado(): void
    {
        $this->actingAs($this->globalAdminB3);

        $antes = Comercio::findOrFail(2);

        $this->post('/admin-global/facturacion/2/pagar', [
            'importe' => 15000,
            'payment_method' => 'efectivo',
        ])->assertForbidden();

        $this->assertSame(0, Payment::count());

        $despues = Comercio::findOrFail(2);
        $this->assertSame(
            $antes->vencimiento_pago->toDateString(),
            $despues->vencimiento_pago->toDateString()
        );
    }

    public function test_no_se_puede_registrar_un_pago_aprobado_con_importe_arbitrario(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        try {
            app(PagoManualService::class)->registrar(
                Comercio::findOrFail(1),
                ['importe' => 1, 'payment_method' => 'efectivo'],
                $this->globalAdminB3,
            );

            $this->fail('Se permitió registrar un pago con importe arbitrario.');
        } catch (PagoManualInvalidoException $e) {
            $this->assertStringContainsString('importe', strtolower($e->getMessage()));
        }

        $this->assertSame(0, Payment::count());
    }

    public function test_no_se_puede_reactivar_un_comercio_con_un_pago_simulado(): void
    {
        $comercio = Comercio::findOrFail(2);
        $comercio->status = 'suspendido';
        $comercio->vencimiento_pago = now()->subDays(10)->toDateString();
        $comercio->save();

        try {
            app(PagoManualService::class)->registrar(
                $comercio,
                ['importe' => 15000, 'payment_method' => 'efectivo'],
                $this->globalAdminB3,
            );

            $this->fail('Se permitió reactivar un comercio con cobros deshabilitados.');
        } catch (PagoManualDeshabilitadoException $e) {
            $this->assertStringContainsString('deshabilitado', strtolower($e->getMessage()));
        }

        $comercio->refresh();

        $this->assertSame('suspendido', $comercio->status);
        $this->assertSame(now()->subDays(10)->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertSame(0, Payment::count());
    }

    // ============================================================
    // 10-12. CÁLCULO DE PERÍODOS
    // ============================================================

    public function test_el_calculo_de_periodos_no_acorta_un_periodo_vigente(): void
    {
        $periodo = app(SuscripcionService::class)->calcularPeriodo(
            Carbon::parse('2026-06-30'),
            Plan::findOrFail(1),
            Carbon::parse('2026-06-10'),
        );

        $this->assertTrue($periodo['continua_periodo_vigente']);
        $this->assertSame('2026-06-30', $periodo['inicio']->toDateString());
        $this->assertSame('2026-07-30', $periodo['fin']->toDateString());
    }

    public function test_el_calculo_despues_del_vencimiento_parte_de_la_fecha_actual(): void
    {
        $periodo = app(SuscripcionService::class)->calcularPeriodo(
            Carbon::parse('2026-06-01'),
            Plan::findOrFail(1),
            Carbon::parse('2026-06-10'),
        );

        $this->assertFalse($periodo['continua_periodo_vigente']);
        $this->assertSame('2026-06-10', $periodo['inicio']->toDateString());
        $this->assertSame('2026-07-10', $periodo['fin']->toDateString());
    }

    public function test_los_periodos_respetan_la_duracion_configurada(): void
    {
        $servicio = app(SuscripcionService::class);

        $planMensual = Plan::findOrFail(1);
        $mensual = $servicio->calcularPeriodo(
            null,
            $planMensual,
            Carbon::parse('2026-06-10'),
        );

        $this->assertSame(1, $mensual['meses']);
        $this->assertSame('2026-07-10', $mensual['fin']->toDateString());

        // El algoritmo lee `duracion_meses` si el plan la define; no asume
        // que todos los planes sean mensuales.
        $planTrimestral = Plan::findOrFail(1);
        $planTrimestral->setAttribute('duracion_meses', 3);

        $trimestral = $servicio->calcularPeriodo(
            null,
            $planTrimestral,
            Carbon::parse('2026-06-10'),
        );

        $this->assertSame(3, $trimestral['meses']);
        $this->assertSame('2026-09-10', $trimestral['fin']->toDateString());
    }

    // ============================================================
    // 13-14. DUPLICADOS E HISTORIAL
    // ============================================================

    public function test_la_estrategia_anti_duplicados_distingue_periodos(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->status = 'activo';
        $comercio->save();

        // Pago aprobado ya existente para el período que el servicio
        // calcularía (2026-06-10 → 2026-07-10).
        $pagoExistente = Payment::create([
            'payable_type' => Comercio::class,
            'payable_id' => $comercio->id,
            'provider' => 'manual',
            'channel' => PaymentChannel::MANUAL,
            'status' => PaymentStatus::APPROVED,
            'amount' => 8000,
            'currency' => 'ARS',
            'period_start' => '2026-06-10',
            'period_end' => '2026-07-10',
            'payment_method' => 'efectivo',
            'approved_at' => now(),
        ]);

        $servicio = app(PagoManualService::class);

        try {
            $servicio->registrar($comercio, [
                'importe' => 8000,
                'payment_method' => 'efectivo',
                'fecha_pago' => '2026-06-10',
            ], $this->globalAdminB3);

            $this->fail('Se permitió duplicar un pago del mismo período.');
        } catch (PagoManualInvalidoException $e) {
            $this->assertStringContainsString('período', $e->getMessage());
        }

        // El mismo comercio con otro período sí puede registrar un pago.
        $pagoExistente->period_end = '2026-08-10';
        $pagoExistente->save();

        $nuevo = $servicio->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-10',
        ], $this->globalAdminB3);

        $this->assertSame('2026-07-10', $nuevo->period_end->toDateString());
        $this->assertSame(2, Payment::count());
    }

    public function test_el_historial_no_inventa_registros(): void
    {
        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AdminGlobal/Facturacion')
            ->has('historial', 0)
        );

        $this->getJson('/admin-global/facturacion/historial')
            ->assertOk()
            ->assertJson([
                'total' => 0,
                'pagos' => [],
            ]);

        $this->assertSame(0, Payment::count());
    }

    // ============================================================
    // 15-18. PERMISOS Y AUDITORÍA
    // ============================================================

    public function test_un_usuario_de_comercio_no_puede_modificar_su_facturacion(): void
    {
        $this->actingAs($this->adminA);

        $antes = Comercio::findOrFail(1);

        $this->post('/admin-global/facturacion/1/pagar', [
            'importe' => 8000,
            'payment_method' => 'efectivo',
        ])->assertForbidden();

        $despues = Comercio::findOrFail(1);

        $this->assertSame(
            $antes->vencimiento_pago->toDateString(),
            $despues->vencimiento_pago->toDateString()
        );
        $this->assertSame('activo', $despues->status);
    }

    public function test_un_usuario_de_comercio_no_puede_consultar_pagos_de_otro_comercio(): void
    {
        $this->actingAs($this->adminA);

        $this->get('/admin-global/facturacion')->assertForbidden();
        $this->getJson('/admin-global/facturacion/historial?comercio_id=2')->assertForbidden();
    }

    public function test_el_administrador_global_conserva_acceso_a_la_informacion_administrativa(): void
    {
        $this->actingAs($this->globalAdminB3);

        $this->get('/admin-global/facturacion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AdminGlobal/Facturacion')
                ->has('comercios')
                ->has('resumen')
            );

        $this->getJson('/admin-global/facturacion/historial')->assertOk();
    }

    public function test_las_acciones_administrativas_quedan_auditadas(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $comercio = Comercio::findOrFail(1);
        $vencimientoAnterior = $comercio->vencimiento_pago->toDateString();

        $pago = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'transferencia',
            'referencia' => 'OP-B3-001',
        ], $this->globalAdminB3);

        $actividad = Activity::query()
            ->where('description', 'pago_manual_registrado')
            ->latest('id')
            ->first();

        $this->assertNotNull($actividad);
        $this->assertSame(Comercio::class, $actividad->subject_type);
        $this->assertSame($comercio->id, $actividad->subject_id);
        $this->assertSame($this->globalAdminB3->id, $actividad->causer_id);

        $propiedades = $actividad->properties;

        $this->assertSame('pago_manual_registrado', $propiedades['accion']);
        $this->assertSame($pago->id, $propiedades['payment_id']);
        $this->assertSame($vencimientoAnterior, $propiedades['vencimiento_anterior']);
        $this->assertNotSame(
            $propiedades['vencimiento_anterior'],
            $propiedades['vencimiento_nuevo']
        );
        $this->assertSame('activo', $propiedades['estado_nuevo']);
    }

    // ============================================================
    // 19-20. CREDENCIALES Y MERCADO PAGO
    // ============================================================

    public function test_el_access_token_de_mercado_pago_no_aparece_en_props_ni_respuestas(): void
    {
        $token = 'APP_USR-TOKEN-SECRETO-B3';

        $comercio = Comercio::findOrFail(1);
        $comercio->mp_access_token = $token;
        $comercio->save();

        PaymentGateway::updateOrCreate(
            ['comercio_id' => $comercio->id, 'provider' => 'mercadopago'],
            [
                'enabled' => true,
                'configuration' => ['access_token' => $token],
            ]
        );

        $this->actingAs($this->adminA);

        $response = $this->get('/configuracion');
        $response->assertOk();
        $response->assertDontSee($token, false);

        $props = $response->viewData('page')['props'];

        $this->assertArrayNotHasKey('mp_access_token', $props['comercio']);
        $this->assertTrue((bool) $props['comercio']['tiene_mp']);

        $gateway = collect($props['comercio']['payment_gateways'] ?? [])
            ->firstWhere('provider', 'mercadopago');

        $this->assertNotNull($gateway);
        $this->assertArrayNotHasKey('access_token', $gateway['configuration']);
        $this->assertTrue((bool) $gateway['configuration']['credenciales_configuradas']);
    }

    public function test_los_botones_de_mercado_pago_simulados_no_aparentan_generar_cobros(): void
    {
        $this->actingAs($this->globalAdminB3);

        $response = $this->postJson('/admin-global/facturacion/1/link-mp');

        $response->assertOk()->assertJson([
            'disponible' => false,
            'cobro_generado' => false,
            'message' => 'Los pagos online estarán disponibles próximamente.',
        ]);

        $this->assertArrayNotHasKey('init_point', $response->json());

        $pagina = $this->get('/admin-global/facturacion');
        $pagina->assertOk();

        $props = $pagina->viewData('page')['props'];

        $this->assertSame(
            'Los pagos online estarán disponibles próximamente.',
            $props['aviso_pagos_online']
        );
        $this->assertFalse($props['cobros_habilitados']);
    }
}
