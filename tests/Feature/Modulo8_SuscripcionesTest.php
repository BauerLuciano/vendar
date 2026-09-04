<?php

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Tests\TestCaseMultiTenant;

class Modulo8_SuscripcionesTest extends TestCaseMultiTenant
{
    // P7.1.1
    public function test_admin_a_puede_ver_mi_plan(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/mi-plan');
        $response->assertOk();
    }

    // P7.1.2
    public function test_user_a_no_puede_ver_mi_plan_por_rol(): void
    {
        $this->actingAsUserA();

        $this->get('/mi-plan')->assertForbidden();
    }

    // P7.1.3
    public function test_admin_a_puede_ver_plan_actual_api(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/api/mi-plan/plan-actual');
        $response->assertOk();
        $response->assertJsonStructure(['plan_id', 'pending_plan_id']);
    }

    // P7.2.1
    public function test_renovar_mismo_plan_suspendido_reactiva_cuenta(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-mismo-plan',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonth()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'plan_reactivated',
            'subject_id' => 1,
            'subject_type' => Comercio::class,
        ]);
    }

    // P7.2.2
    public function test_renovar_mismo_plan_vencido_reactiva_cuenta(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->subDays(5)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-mismo-plan-vencido',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonth()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);
    }

    // P7.2.3
    public function test_renovar_mismo_plan_con_cuenta_al_dia_no_adelanta_vencimiento(): void
    {
        $vencimientoOriginal = now()->addDays(20)->toDateString();
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => $vencimientoOriginal,
            'pending_plan_id' => 1,
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-mismo-plan-al-dia',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);
        $response->assertJsonPath('plan.id', 1);
        $response->assertJsonPath('plan.nombre', Plan::find(1)->nombre);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame($vencimientoOriginal, $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);
    }

    // P7.2.4
    public function test_webhook_renueva_mismo_plan_suspendido_reactiva_cuenta(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-renov-1',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 8000,
            ]),
        ]);

        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $response = $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-renov-1'],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonth()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);

        $this->assertDatabaseHas('payments', [
            'provider' => 'mercadopago',
            'gateway_transaction_id' => 'pay-renov-1',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'plan_reactivated_via_webhook',
            'subject_id' => 1,
            'subject_type' => Comercio::class,
        ]);
    }

    // P7.2.5
    public function test_webhook_cambio_de_plan_desde_suspendido_reactiva_cuenta(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-upgrade-1',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 15000,
            ]),
        ]);

        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 2,
        ]);

        $response = $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-upgrade-1'],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $comercio->refresh();
        $this->assertSame(2, (int) $comercio->plan_id);
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonth()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);

        $this->assertDatabaseHas('payments', [
            'provider' => 'mercadopago',
            'gateway_transaction_id' => 'pay-upgrade-1',
            'status' => 'approved',
        ]);
    }

    // P7.2.6
    public function test_cuenta_suspendida_por_vencimiento_muestra_dias_vencidos_positivos(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/cuenta-suspendida')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suspendido')
                ->where('suspendida_por_vencimiento', true)
                ->where('dias_vencidos', 5));
    }

    // P7.2.7
    public function test_cuenta_suspendida_manualmente_no_muestra_dias_vencidos_negativos(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->addDays(304)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/cuenta-suspendida')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suspendido')
                ->where('suspendida_por_vencimiento', false)
                ->where('dias_vencidos', null));
    }

    // P7.2.8
    public function test_cambio_de_plan_desde_suspendido_reactiva_cuenta(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-upgrade-confirm',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 15000,
            ]),
        ]);

        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 2,
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 2,
            'payment_id' => 'pay-upgrade-confirm',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $comercio->refresh();
        $this->assertSame(2, (int) $comercio->plan_id);
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonth()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);
    }

    // P7.3.1
    public function test_generar_preferencia_apunta_back_urls_a_public_url_dns_para_que_mp_las_acepte(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
            'services.mercadopago.allowed_return_origins' => ['http://localhost'],
            'app.url' => 'http://localhost',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-p7-3-1',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-p7-3-1',
            ]),
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/mi-plan/pagar', [
            'plan_id' => 1,
            'origin' => 'http://localhost',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['init_point']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['back_urls']['success'] === 'https://brandi-palmar-pickily.ngrok-free.dev/retorno?pago=exito&plan_id=1'
                && $payload['back_urls']['failure'] === 'https://brandi-palmar-pickily.ngrok-free.dev/retorno?pago=error'
                && $payload['back_urls']['pending'] === 'https://brandi-palmar-pickily.ngrok-free.dev/retorno?pago=pendiente'
                && $payload['notification_url'] === 'https://brandi-palmar-pickily.ngrok-free.dev/api/mercadopago/notificacion?tipo=plan'
                && $payload['auto_return'] === 'approved';
        });
    }

    // P7.3.5
    public function test_generar_preferencia_con_origin_https_envia_auto_return(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
            'app.url' => 'http://localhost',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-p7-3-5',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-p7-3-5',
            ]),
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/mi-plan/pagar', [
            'plan_id' => 1,
            'origin' => 'https://brandi-palmar-pickily.ngrok-free.dev',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['back_urls']['success'] === 'https://brandi-palmar-pickily.ngrok-free.dev/retorno?pago=exito&plan_id=1'
                && $payload['auto_return'] === 'approved';
        });
    }

    // P7.3.2
    public function test_generar_preferencia_rechaza_origin_arbitrario(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.allowed_return_origins' => ['http://localhost'],
            'app.url' => 'http://localhost',
            'services.mercadopago.public_url' => 'http://localhost',
        ]);

        Http::fake();

        $this->actingAsAdminA();

        $response = $this->postJson('/mi-plan/pagar', [
            'plan_id' => 1,
            'origin' => 'https://evil.example.com',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Origin no permitido']);

        Http::assertNothingSent();
    }

    // P7.3.3
    public function test_generar_preferencia_con_origin_de_vendar_app_test_es_valido(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
            'app.url' => 'http://localhost',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-p7-3-3',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-p7-3-3',
            ]),
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/mi-plan/pagar', [
            'plan_id' => 1,
            'origin' => 'http://vendar-app.test',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request->data()['back_urls']['success'] === 'https://brandi-palmar-pickily.ngrok-free.dev/retorno?pago=exito&plan_id=1';
        });
    }

    // P7.3.4
    public function test_generar_preferencia_sin_origin_usa_origin_por_defecto_de_app_url(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
            'app.url' => 'http://localhost',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-p7-3-4',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-p7-3-4',
            ]),
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/mi-plan/pagar', ['plan_id' => 1]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request->data()['back_urls']['success'] === 'https://brandi-palmar-pickily.ngrok-free.dev/retorno?pago=exito&plan_id=1';
        });
    }

    // P7.4.1
    public function test_webhook_duplicado_mismo_pago_no_aplica_dos_veces(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-dup-1',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 8000,
            ]),
        ]);

        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $payload = ['tipo' => 'plan', 'data' => ['id' => 'pay-dup-1']];

        $this->postJson('/api/mercadopago/notificacion', $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_upgraded']);

        $this->postJson('/api/mercadopago/notificacion', $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonth()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'provider' => 'mercadopago',
            'gateway_transaction_id' => 'pay-dup-1',
            'status' => 'approved',
        ]);
    }

    // P7.4.2
    public function test_confirmar_upgrade_y_webhook_del_mismo_pago_son_idempotentes(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-mixto-1',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 15000,
            ]),
        ]);

        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(3)->toDateString(),
            'pending_plan_id' => 2,
        ]);

        $this->actingAsAdminA();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 2,
            'payment_id' => 'pay-mixto-1',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-mixto-1'],
        ])->assertOk()->assertJson(['status' => 'already_processed']);

        $comercio->refresh();
        $this->assertSame(2, (int) $comercio->plan_id);
        $this->assertSame('activo', $comercio->status);
        $this->assertNull($comercio->pending_plan_id);

        $this->assertDatabaseCount('payments', 1);
    }

    // P7.5.1
    public function test_retorno_approved_redirige_a_mi_plan_de_la_app_con_payment_id(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('/retorno')
            ->assertRedirect('http://localhost/mi-plan?pago=exito');
    }

    // P7.5.2
    public function test_retorno_con_parametros_de_mp_reconstruye_pago_y_plan(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('/retorno?status=approved&payment_id=555&external_reference=1')
            ->assertRedirect('http://localhost/mi-plan?pago=exito&payment_id=555');
    }

    // P7.5.3
    public function test_retorno_approved_con_plan_id_redirige_con_plan(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('/retorno?status=approved&pago=exito&plan_id=1&payment_id=777')
            ->assertRedirect('http://localhost/mi-plan?pago=exito&plan_id=1&payment_id=777');
    }

    // P7.5.4
    public function test_retorno_rejected_redirige_como_error(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('/retorno?collection_status=rejected&payment_id=999')
            ->assertRedirect('http://localhost/mi-plan?pago=error&payment_id=999');
    }

    // P7.5.5
    public function test_retorno_pending_se_transforma_en_estado_pendiente(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('/retorno?status=pending&plan_id=2')
            ->assertRedirect('http://localhost/mi-plan?pago=pendiente&plan_id=2');
    }

    // P7.6.1
    public function test_dashboard_no_muestra_alerta_con_vencimiento_lejano(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->addDays(11)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta', null));
    }

    // P7.6.2
    public function test_dashboard_muestra_alerta_a_los_10_dias(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta.mostrar', true)
                ->where('suscripcionAlerta.nivel', 'aviso')
                ->where('suscripcionAlerta.dias_restantes', 10)
                ->where('suscripcionAlerta.plan_nombre', Plan::find(1)->nombre)
                ->where('suscripcionAlerta.mensaje', fn ($msg) => str_contains($msg, '10 días')
                    && str_contains($msg, 'Recordá realizar el pago')));
    }

    // P7.6.3
    public function test_dashboard_muestra_alerta_a_los_7_dias(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->addDays(7)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta.mostrar', true)
                ->where('suscripcionAlerta.nivel', 'aviso')
                ->where('suscripcionAlerta.mensaje', fn ($msg) => str_contains($msg, '7 días')
                    && str_contains($msg, 'Recordá realizar el pago')));
    }

    // P7.6.4
    public function test_dashboard_muestra_advertencia_a_los_3_dias(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta.mostrar', true)
                ->where('suscripcionAlerta.nivel', 'advertencia')
                ->where('suscripcionAlerta.mensaje', fn ($msg) => str_contains($msg, '3 días')
                    && str_contains($msg, 'evitar la suspensión')));
    }

    // P7.6.5
    public function test_dashboard_muestra_urgencia_a_1_dia(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->addDay()->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta.mostrar', true)
                ->where('suscripcionAlerta.nivel', 'urgente')
                ->where('suscripcionAlerta.mensaje', fn ($msg) => str_contains($msg, 'mañana')
                    && str_contains($msg, 'evitar la suspensión')));
    }

    // P7.6.6
    public function test_dashboard_muestra_urgencia_el_mismo_dia_de_vencimiento(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta.mostrar', true)
                ->where('suscripcionAlerta.nivel', 'urgente')
                ->where('suscripcionAlerta.mensaje', fn ($msg) => str_contains($msg, 'vence hoy')));
    }

    // P7.6.7
    public function test_dashboard_muestra_alerta_de_suscripcion_vencida(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => now()->subDays(2)->toDateString(),
        ]);

        // Sucursal inexistente: la cuenta vencida queda bloqueada por
        // VerificarEstadoCuenta y no llega al Dashboard; con una sucursal
        // resuelta probamos la lógica del nivel "vencido".
        session(['sucursal_activa_id' => 999999]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta.mostrar', true)
                ->where('suscripcionAlerta.nivel', 'vencido')
                ->where('suscripcionAlerta.mensaje', fn ($msg) => str_contains($msg, 'está vencida')
                    && str_contains($msg, 'Renová tu plan')));
    }

    // P7.6.8
    public function test_dashboard_no_muestra_alerta_sin_vencimiento(): void
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->update([
            'status' => 'activo',
            'vencimiento_pago' => null,
        ]);

        $this->actingAsAdminA();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('suscripcionAlerta', null));
    }
}
