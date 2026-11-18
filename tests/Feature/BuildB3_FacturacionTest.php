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
use App\Models\Venta;
use App\Services\Suscripcion\PagoManualService;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCaseMultiTenant;

class BuildB3_FacturacionTest extends TestCaseMultiTenant
{
    private User $globalAdminB3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalAdminB3 = new User;
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

        // El día del vencimiento todavía hay acceso. Se muestra Por Vencer
        // (igual que SuscripcionService), nunca Vencido.
        $this->assertSame('Por Vencer', $comercios[1]['estado_cobro']);
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
        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $periodo = app(SuscripcionService::class)->calcularPeriodo(
            Carbon::parse('2026-06-30'),
            Plan::findOrFail(1),
            Carbon::parse('2026-06-10'),
        );

        $this->assertTrue($periodo['continua_periodo_vigente']);
        $this->assertSame('2026-06-30', $periodo['inicio']->toDateString());
        $this->assertSame('2026-07-30', $periodo['fin']->toDateString());

        $this->travelBack();
    }

    public function test_el_calculo_despues_del_vencimiento_parte_de_la_fecha_actual(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        // La fecha de pago es anterior a hoy (comprobante cargado tarde). El
        // período igual arranca hoy: no se regalan ni se quitan días.
        $periodo = app(SuscripcionService::class)->calcularPeriodo(
            Carbon::parse('2026-06-01'),
            Plan::findOrFail(1),
            Carbon::parse('2026-06-05'),
        );

        $this->assertFalse($periodo['continua_periodo_vigente']);
        $this->assertSame('2026-06-10', $periodo['inicio']->toDateString());
        $this->assertSame('2026-07-10', $periodo['fin']->toDateString());

        $this->travelBack();
    }

    public function test_los_periodos_respetan_la_duracion_configurada(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

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

        $this->travelBack();
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

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

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

        $this->travelBack();
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

    // ============================================================
    // 21-30. COBERTURA AMPLIADA B3
    // ============================================================

    public function test_la_pantalla_muestra_el_plan_y_precio_configurado_reales(): void
    {
        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $comercio = collect($props['comercios'])->firstWhere('id', 1);

        $this->assertSame('Plan Básico', $comercio['plan']);
        $this->assertSame('basico', $comercio['slug']);
        $this->assertSame(8000.0, $comercio['precio_configurado']);
        $this->assertTrue($comercio['precio_es_referencia']);
    }

    public function test_sin_plan_contratado_no_se_muestra_pro_ni_precio_cero(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->plan = 'pro';
        $comercio->plan_id = null;
        $comercio->save();

        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $comercioData = collect($props['comercios'])->firstWhere('id', 1);

        $this->assertSame('Sin plan', $comercioData['plan']);
        $this->assertNotSame('PRO', $comercioData['plan']);
        $this->assertNull($comercioData['precio_configurado']);
        $this->assertNotSame(0.0, $comercioData['precio_configurado']);
        $this->assertSame('pro', $comercioData['plan_legacy']);
    }

    public function test_la_pantalla_de_suspension_no_muestra_el_slug_legacy_como_plan(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->plan = 'pro';
        $comercio->plan_id = null;
        $comercio->status = 'suspendido';
        $comercio->save();

        $this->actingAs($this->adminA);

        $this->get('/cuenta-suspendida')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suspendido')
                ->where('comercio.plan', 'Sin plan')
            );
    }

    public function test_un_pago_habilitado_persiste_todos_los_datos_y_reactiva(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(2);
        $comercio->status = 'suspendido';
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->save();

        $pago = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 15000,
            'payment_method' => 'transferencia',
            'referencia' => 'TRF-123',
            'fecha_pago' => '2026-06-10',
            'idempotency_key' => 'pago-b3-2',
        ], $this->globalAdminB3);

        $this->assertSame(Comercio::class, $pago->payable_type);
        $this->assertSame($comercio->id, $pago->payable_id);
        $this->assertSame('manual', $pago->provider);
        $this->assertSame(PaymentChannel::MANUAL, $pago->channel);
        $this->assertSame(PaymentStatus::APPROVED, $pago->status);
        $this->assertSame('15000.00', $pago->amount);
        $this->assertSame('ARS', $pago->currency);
        $this->assertSame('2026-06-10', $pago->period_start->toDateString());
        $this->assertSame('2026-07-10', $pago->period_end->toDateString());
        $this->assertSame('transferencia', $pago->payment_method);
        $this->assertSame($this->globalAdminB3->id, $pago->created_by_id);
        $this->assertSame('TRF-123', $pago->reference);
        $this->assertSame('pago-b3-2', $pago->idempotency_key);
        $this->assertSame('2026-06-10', $pago->approved_at->toDateString());
        $this->assertSame('suscripcion', $pago->metadata['tipo']);
        $this->assertSame('manual', $pago->metadata['origen']);
        $this->assertNotNull($pago->created_at);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame('2026-07-10', $comercio->vencimiento_pago->toDateString());

        $this->travelBack();
    }

    public function test_un_pago_anticipado_prorroga_desde_el_vencimiento_vigente(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->status = 'activo';
        $comercio->vencimiento_pago = '2026-06-30';
        $comercio->save();

        $pago = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-10',
        ], $this->globalAdminB3);

        // El ejemplo de la auditoría: vence 30/06 y paga 10/06. No se acorta
        // el período vigente: el nuevo cubre 30/06 → 30/07.
        $this->assertSame('2026-06-30', $pago->period_start->toDateString());
        $this->assertSame('2026-07-30', $pago->period_end->toDateString());

        $comercio->refresh();
        $this->assertSame('2026-07-30', $comercio->vencimiento_pago->toDateString());

        $this->travelBack();
    }

    public function test_un_pago_con_fecha_pasada_no_acorta_el_nuevo_periodo(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->status = 'suspendido';
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->save();

        $pago = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-05',
        ], $this->globalAdminB3);

        // La fecha del comprobante queda registrada, pero el período arranca
        // hoy para no perder días de suscripción.
        $this->assertSame('2026-06-10', $pago->period_start->toDateString());
        $this->assertSame('2026-07-10', $pago->period_end->toDateString());
        $this->assertSame('2026-06-05', $pago->approved_at->toDateString());

        $this->travelBack();
    }

    public function test_la_fecha_de_pago_no_puede_ser_futura(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        try {
            app(PagoManualService::class)->registrar(Comercio::findOrFail(1), [
                'importe' => 8000,
                'payment_method' => 'efectivo',
                'fecha_pago' => '2026-06-11',
            ], $this->globalAdminB3);

            $this->fail('Se permitió registrar un pago con fecha futura.');
        } catch (PagoManualInvalidoException $e) {
            $this->assertStringContainsString('futura', strtolower($e->getMessage()));
        } finally {
            $this->travelBack();
        }

        $this->assertSame(0, Payment::count());
    }

    public function test_el_reintento_con_la_misma_clave_devuelve_el_mismo_pago(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-30';
        $comercio->save();

        $servicio = app(PagoManualService::class);
        $datos = [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-10',
            'idempotency_key' => 'idem-b3-1',
        ];

        $primero = $servicio->registrar($comercio, $datos, $this->globalAdminB3);
        $segundo = $servicio->registrar($comercio, $datos, $this->globalAdminB3);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, Payment::count());

        $comercio->refresh();
        $this->assertSame('2026-07-30', $comercio->vencimiento_pago->toDateString());

        $this->travelBack();
    }

    public function test_una_clave_de_idempotencia_de_otro_comercio_no_se_reutiliza(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        Payment::create([
            'payable_type' => Comercio::class,
            'payable_id' => 2,
            'provider' => 'manual',
            'channel' => PaymentChannel::MANUAL,
            'status' => PaymentStatus::APPROVED,
            'amount' => 15000,
            'currency' => 'ARS',
            'period_start' => '2026-06-10',
            'period_end' => '2026-07-10',
            'payment_method' => 'efectivo',
            'idempotency_key' => 'clave-compartida',
            'approved_at' => now(),
            'metadata' => ['tipo' => 'suscripcion'],
        ]);

        $comercio = Comercio::findOrFail(1);
        $vencimientoAntes = $comercio->vencimiento_pago->toDateString();

        try {
            app(PagoManualService::class)->registrar($comercio, [
                'importe' => 8000,
                'payment_method' => 'efectivo',
                'idempotency_key' => 'clave-compartida',
            ], $this->globalAdminB3);

            $this->fail('Se reutilizó una clave de idempotencia de otro comercio.');
        } catch (PagoManualInvalidoException $e) {
            $this->assertStringContainsString('otro comercio', $e->getMessage());
        }

        $this->assertSame(1, Payment::count());

        $comercio->refresh();
        $this->assertSame($vencimientoAntes, $comercio->vencimiento_pago->toDateString());

        $this->travelBack();
    }

    public function test_un_pago_de_mercado_pago_del_mismo_periodo_tambien_bloquea_el_manual(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->save();

        Payment::create([
            'payable_type' => Comercio::class,
            'payable_id' => 1,
            'provider' => 'mercadopago',
            'channel' => PaymentChannel::API,
            'status' => PaymentStatus::APPROVED,
            'amount' => 8000,
            'currency' => 'ARS',
            'period_start' => '2026-06-10',
            'period_end' => '2026-07-10',
            'gateway_transaction_id' => 'mp-b3-duplicado',
            'approved_at' => now(),
            'metadata' => ['tipo' => 'suscripcion'],
        ]);

        try {
            app(PagoManualService::class)->registrar($comercio, [
                'importe' => 8000,
                'payment_method' => 'efectivo',
                'fecha_pago' => '2026-06-10',
            ], $this->globalAdminB3);

            $this->fail('Se duplicó un período ya aprobado por Mercado Pago.');
        } catch (PagoManualInvalidoException $e) {
            $this->assertStringContainsString('período', $e->getMessage());
        }

        $this->assertSame(1, Payment::count());

        $this->travelBack();
    }

    public function test_un_suspendido_con_vencimiento_futuro_sigue_listado_pero_no_es_mora(): void
    {
        $comercio = Comercio::findOrFail(2);
        $comercio->status = 'suspendido';
        $comercio->vencimiento_pago = now()->addDays(15)->toDateString();
        $comercio->save();

        $this->actingAs($this->globalAdminB3);

        $response = $this->get('/admin-global/facturacion');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $comercios = collect($props['comercios'])->keyBy('id');

        $this->assertSame('Suspendido', $comercios[2]['estado_cobro']);
        $this->assertSame(1, $props['resumen']['suspendidos']);
        $this->assertSame(0, $props['resumen']['en_mora']);
        $this->assertSame(0, $props['resumen']['clientes_morosos']);
    }

    public function test_el_historial_muestra_solo_pagos_de_suscripcion_con_sus_datos(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->save();

        $pago = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'transferencia',
            'referencia' => 'HIST-1',
            'fecha_pago' => '2026-06-10',
        ], $this->globalAdminB3);

        // Pago de una venta: no debe mezclarse con la facturación SaaS.
        Payment::create([
            'payable_type' => Venta::class,
            'payable_id' => 1,
            'provider' => 'manual',
            'channel' => PaymentChannel::MANUAL,
            'status' => PaymentStatus::APPROVED,
            'amount' => 500,
            'currency' => 'ARS',
            'metadata' => ['tipo' => 'venta'],
            'approved_at' => now(),
        ]);

        $this->actingAs($this->globalAdminB3);

        $response = $this->getJson('/admin-global/facturacion/historial');
        $response->assertOk()->assertJson(['total' => 1]);

        $pagos = $response->json('pagos');

        $this->assertSame($pago->id, $pagos[0]['id']);
        $this->assertSame($comercio->id, $pagos[0]['comercio_id']);
        $this->assertSame($comercio->nombre, $pagos[0]['comercio']);
        $this->assertEquals(8000.0, $pagos[0]['importe']);
        $this->assertSame('ARS', $pagos[0]['moneda']);
        $this->assertSame('transferencia', $pagos[0]['metodo']);
        $this->assertSame('10/06/2026', $pagos[0]['periodo_inicio']);
        $this->assertSame('10/07/2026', $pagos[0]['periodo_fin']);
        $this->assertSame('approved', $pagos[0]['estado']);
        $this->assertSame($this->globalAdminB3->name, $pagos[0]['responsable']);
        $this->assertSame('HIST-1', $pagos[0]['referencia']);

        $this->travelBack();
    }

    public function test_otros_roles_del_comercio_tampoco_acceden_a_facturacion_global(): void
    {
        $this->actingAs($this->userA);

        $this->get('/admin-global/facturacion')->assertForbidden();
        $this->getJson('/admin-global/facturacion/historial')->assertForbidden();
        $this->post('/admin-global/facturacion/1/pagar', [
            'importe' => 8000,
            'payment_method' => 'efectivo',
        ])->assertForbidden();
    }

    public function test_un_invitado_no_accede_a_facturacion_global(): void
    {
        $this->get('/admin-global/facturacion')->assertRedirect('/login');
        $this->getJson('/admin-global/facturacion/historial')->assertUnauthorized();
    }

    public function test_el_pago_de_mercado_pago_persiste_el_periodo_y_el_responsable(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-30';
        $comercio->save();

        $this->actingAs($this->globalAdminB3);

        app(SuscripcionService::class)->aplicarPago(
            $comercio,
            Plan::findOrFail(1),
            'mp-b3-periodo',
            8000,
            'confirmar_upgrade',
        );

        $pago = Payment::query()
            ->where('gateway_transaction_id', 'mp-b3-periodo')
            ->firstOrFail();

        $this->assertSame('2026-06-30', $pago->period_start->toDateString());
        $this->assertSame('2026-07-30', $pago->period_end->toDateString());
        $this->assertSame('ARS', $pago->currency);
        $this->assertSame($this->globalAdminB3->id, $pago->created_by_id);
        $this->assertSame('suscripcion', $pago->metadata['tipo']);

        $comercio->refresh();
        $this->assertSame('2026-07-30', $comercio->vencimiento_pago->toDateString());

        $this->travelBack();
    }

    public function test_un_comercio_suspendido_puede_cancelar_un_pago_en_vuelo(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->status = 'suspendido';
        $comercio->vencimiento_pago = now()->subDays(5)->toDateString();
        $comercio->pending_plan_id = 2;
        $comercio->save();

        $this->actingAs($this->adminA);

        $this->postJson('/api/mi-plan/cancelar-pago')
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $comercio->refresh();
        $this->assertNull($comercio->pending_plan_id);
    }

    public function test_el_periodo_del_dia_de_vencimiento_no_se_acorta(): void
    {
        $this->travelTo(Carbon::parse('2026-06-30 10:00:00'));

        $periodo = app(SuscripcionService::class)->calcularPeriodo(
            Carbon::parse('2026-06-30'),
            Plan::findOrFail(1),
            Carbon::parse('2026-06-30'),
        );

        $this->assertTrue($periodo['continua_periodo_vigente']);
        $this->assertSame('2026-06-30', $periodo['inicio']->toDateString());
        $this->assertSame('2026-07-30', $periodo['fin']->toDateString());

        $this->travelBack();
    }

    // ============================================================
    // 15-18. B3.1: A1, M1 Y M2
    // ============================================================

    public function test_el_pago_manual_exitoso_devuelve_flash_de_exito(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->status = 'suspendido';
        $comercio->save();

        $this->actingAs($this->globalAdminB3);

        $response = $this->from('/admin-global/facturacion')->post(
            '/admin-global/facturacion/1/pagar',
            [
                'importe' => 8000,
                'payment_method' => 'efectivo',
                'referencia' => 'M1-EXITO',
                'fecha_pago' => '2026-06-10',
                'idempotency_key' => 'm1-exito',
            ],
        );

        $response->assertRedirect('/admin-global/facturacion');
        $response->assertSessionHas('exito', function (string $mensaje) use ($comercio) {
            return str_contains($mensaje, 'Pago #')
                && str_contains($mensaje, $comercio->nombre);
        });

        $this->assertDatabaseHas('payments', [
            'idempotency_key' => 'm1-exito',
            'status' => 'approved',
        ]);

        $this->travelBack();
    }

    public function test_el_pago_manual_invalido_devuelve_error_de_negocio(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->save();

        // Pago aprobado que ocupa exactamente el período que intentará
        // registrar el formulario: el servicio debe rechazarlo y el
        // controlador debe devolver el error en la bolsa `pago`.
        Payment::create([
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
            'metadata' => ['tipo' => 'suscripcion', 'origen' => 'manual'],
        ]);

        $this->actingAs($this->globalAdminB3);

        $response = $this->from('/admin-global/facturacion')->post(
            '/admin-global/facturacion/1/pagar',
            [
                'importe' => 8000,
                'payment_method' => 'efectivo',
                'fecha_pago' => '2026-06-10',
                'idempotency_key' => 'm1-error',
            ],
        );

        $response->assertRedirect('/admin-global/facturacion');
        $response->assertSessionHasErrors([
            'pago' => 'Ya existe un pago aprobado para ese período.',
        ]);

        $this->assertDatabaseMissing('payments', [
            'idempotency_key' => 'm1-error',
        ]);

        $this->travelBack();
    }

    public function test_un_pago_manual_nuevo_limpia_la_preferencia_en_vuelo(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->pending_plan_id = 2;
        $comercio->save();

        $suscripcion = app(SuscripcionService::class);
        $suscripcion->marcarPagoEnVuelo($comercio->id, 2);
        $suscripcion->recordarCheckoutEnVuelo(
            $comercio->id,
            'https://mp.example/checkout/viejo',
        );

        $this->assertNotNull($suscripcion->pagoEnVuelo($comercio->id));

        app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-10',
            'idempotency_key' => 'm2-limpia',
        ], $this->globalAdminB3);

        $comercio->refresh();

        $this->assertNull($comercio->pending_plan_id);
        $this->assertNull(
            $suscripcion->pagoEnVuelo($comercio->id),
            'El pago manual debe invalidar la preferencia de Mercado Pago en vuelo.',
        );

        $this->travelBack();
    }

    public function test_un_reintento_idempotente_no_invalida_una_preferencia_nueva(): void
    {
        config([
            'vendar.facturacion.cobros_habilitados' => true,
            'vendar.facturacion.precios_confirmados' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));

        $comercio = Comercio::findOrFail(1);
        $comercio->vencimiento_pago = '2026-06-01';
        $comercio->save();

        $pago = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-10',
            'idempotency_key' => 'm2-reintento',
        ], $this->globalAdminB3);

        // Después del pago manual se genera una preferencia nueva legítima.
        $comercio->refresh();
        $comercio->pending_plan_id = 2;
        $comercio->save();

        $suscripcion = app(SuscripcionService::class);
        $suscripcion->marcarPagoEnVuelo($comercio->id, 2);
        $suscripcion->recordarCheckoutEnVuelo(
            $comercio->id,
            'https://mp.example/checkout/nuevo',
        );

        $reintento = app(PagoManualService::class)->registrar($comercio, [
            'importe' => 8000,
            'payment_method' => 'efectivo',
            'fecha_pago' => '2026-06-10',
            'idempotency_key' => 'm2-reintento',
        ], $this->globalAdminB3);

        $this->assertSame($pago->id, $reintento->id);
        $this->assertSame(2, (int) $comercio->fresh()->pending_plan_id);
        $this->assertNotNull(
            $suscripcion->pagoEnVuelo($comercio->id),
            'Un reintento idempotente no debe borrar una preferencia generada después.',
        );

        $suscripcion->limpiarPagoEnVuelo($comercio->id);

        $this->travelBack();
    }

    public function test_un_pago_aprobado_sin_pending_plan_id_se_registra_para_conciliacion(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => '2026-06-30',
            'pending_plan_id' => null,
        ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-superseded-b31',
                'status' => 'approved',
                'external_reference' => (string) $comercio->id,
                'transaction_amount' => 8000,
            ]),
        ]);

        $payload = [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-superseded-b31'],
        ];

        $this->postJson('/api/mercadopago/notificacion', $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $pago = Payment::query()
            ->where('provider', 'mercadopago')
            ->where('gateway_transaction_id', 'pay-superseded-b31')
            ->firstOrFail();

        $this->assertSame(Comercio::class, $pago->payable_type);
        $this->assertSame($comercio->id, $pago->payable_id);
        $this->assertSame(PaymentChannel::API, $pago->channel);
        $this->assertSame(PaymentStatus::APPROVED, $pago->status);
        $this->assertSame('8000.00', $pago->amount);
        $this->assertSame('superseded', $pago->metadata['conciliacion']);
        $this->assertSame('sin_pending_plan_id', $pago->metadata['motivo']);
        $this->assertNull($pago->period_start);
        $this->assertNull($pago->period_end);

        $comercio->refresh();
        $this->assertSame(1, (int) $comercio->plan_id);
        $this->assertSame('2026-06-30', $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);

        // El webhook duplicado no debe crear un segundo registro.
        $this->postJson('/api/mercadopago/notificacion', $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $this->assertSame(
            1,
            Payment::where('gateway_transaction_id', 'pay-superseded-b31')->count(),
        );
    }
}
