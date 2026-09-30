<?php

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\Plan;
use App\Services\Suscripcion\SuscripcionService;
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

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Simula un pago de Mercado Pago aprobado.
     */
    private function fakePagoAprobado(string $paymentId, float $monto = 8000, string $referenceId = '1'): void
    {
        config(['services.mercadopago.access_token' => 'TEST-1234567890']);

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => $paymentId,
                'status' => 'approved',
                'external_reference' => $referenceId,
                'transaction_amount' => $monto,
            ]),
        ]);
    }

    private function prepararComercio(array $atributos): Comercio
    {
        $comercio = Comercio::findOrFail(1);
        $comercio->forceFill($atributos)->save();

        return $comercio->fresh();
    }

    // P7.2.1
    public function test_renovar_mismo_plan_suspendido_reactiva_cuenta(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->fakePagoAprobado('pago-mismo-plan');
        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-mismo-plan',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $comercio->vencimiento_pago->toDateString());
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
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'vencimiento_pago' => now()->subDays(5)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->fakePagoAprobado('pago-mismo-plan-vencido');
        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-mismo-plan-vencido',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);
    }

    /**
     * P7.2.3 (redefinido): renovar el plan actual con la suscripción todavía
     * vigente ahora prorroga el período, en lugar de cobrar sin extender nada.
     */
    public function test_renovar_mismo_plan_con_cuenta_al_dia_extiende_el_vencimiento(): void
    {
        $vencimientoOriginal = now()->addDays(20)->toDateString();

        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'vencimiento_pago' => $vencimientoOriginal,
            'pending_plan_id' => 1,
        ]);

        $this->fakePagoAprobado('pago-mismo-plan-al-dia');
        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-mismo-plan-al-dia',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already_upgraded']);
        $response->assertJsonPath('intento', 'renovacion');
        $response->assertJsonPath('plan.id', 1);
        $response->assertJsonPath('plan.nombre', Plan::find(1)->nombre);

        $comercio->refresh();
        $this->assertSame('activo', $comercio->status);
        $this->assertNotSame($vencimientoOriginal, $comercio->vencimiento_pago->toDateString());
        $this->assertSame(
            now()->addDays(20)->addMonthNoOverflow()->toDateString(),
            $comercio->vencimiento_pago->toDateString()
        );
        $this->assertNull($comercio->pending_plan_id);

        // El importe cobrado queda registrado: antes el camino HTTP lo dejaba
        // en NULL.
        $this->assertDatabaseHas('payments', [
            'provider' => 'mercadopago',
            'gateway_transaction_id' => 'pago-mismo-plan-al-dia',
            'amount' => 8000,
        ]);
    }

    /**
     * El caso que originó el reporte: plan próximo a vencer, botón "Renovar"
     * disponible y prórroga efectiva de un mes desde el vencimiento vigente.
     */
    public function test_renovar_plan_proximo_a_vencer_extiende_un_mes(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'vencimiento_pago' => now()->addDays(5)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->fakePagoAprobado('pago-proximo');
        $this->actingAsAdminA();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-proximo',
        ])->assertOk();

        $comercio->refresh();
        $this->assertSame(
            now()->addDays(5)->addMonthNoOverflow()->toDateString(),
            $comercio->vencimiento_pago->toDateString()
        );
        $this->assertSame('activo', $comercio->status);
    }

    /**
     * Regresión de seguridad: renovar el plan propio con un pago que no
     * corresponde (o no existe) no debe reactivar la cuenta ni alargar el
     * vencimiento. Antes esta ruta no verificaba el pago con Mercado Pago.
     */
    public function test_renovar_mismo_plan_con_pago_de_otro_comercio_es_rechazado(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->fakePagoAprobado('pago-ajeno', 8000, '999');
        $this->actingAsAdminA();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-ajeno',
        ])->assertStatus(403)->assertJsonPath('error', 'El pago no corresponde a este comercio');

        $comercio->refresh();
        $this->assertSame('suspendido', $comercio->status);
        $this->assertSame(now()->subDays(10)->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertDatabaseMissing('payments', ['gateway_transaction_id' => 'pago-ajeno']);
    }

    public function test_renovar_mismo_plan_con_pago_rechazado_es_rechazado(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        config(['services.mercadopago.access_token' => 'TEST-1234567890']);
        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pago-rechazado',
                'status' => 'rejected',
                'external_reference' => '1',
                'transaction_amount' => 8000,
            ]),
        ]);

        $this->actingAsAdminA();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-rechazado',
        ])->assertStatus(400)->assertJsonStructure(['error']);

        $comercio->refresh();
        $this->assertSame('suspendido', $comercio->status);
    }

    /**
     * El 502 es un caso distinto al 400: falló la consulta a Mercado Pago, así
     * que el pago puede estar aprobado igual y no debe tratarse como rechazado.
     * El frontend lo muestra como "no pudimos verificar" y ofrece consultar el
     * estado bajo demanda, en vez de afirmar que el pago falló.
     */
    public function test_confirmar_con_mp_inaccesible_devuelve_502_y_no_altera_la_suscripcion(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        config(['services.mercadopago.access_token' => 'TEST-1234567890']);
        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response(['message' => 'bad gateway'], 502),
        ]);

        $this->actingAsAdminA();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-verificacion-fallida',
        ])
            ->assertStatus(502)
            ->assertJsonPath('error', 'No se pudo verificar el pago');

        // Un fallo de verificación no puede alterar nada: ni reactivar la
        // cuenta, ni alargar el vencimiento, ni registrar un pago.
        $comercio->refresh();
        $this->assertSame('suspendido', $comercio->status);
        $this->assertSame(now()->subDays(10)->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertDatabaseMissing('payments', ['gateway_transaction_id' => 'pago-verificacion-fallida']);
    }

    /**
     * Contrato con el frontend: todo error de confirmación tiene que traer un
     * mensaje legible en `error`. Si algún día una rama devuelve un 400/403/502
     * sin mensaje, el usuario vería un texto genérico en vez del motivo real.
     */
    public function test_los_errores_de_confirmacion_siempre_traen_mensaje_legible(): void
    {
        $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        config(['services.mercadopago.access_token' => 'TEST-1234567890']);
        $this->actingAsAdminA();

        // 403: el pago es de otro comercio.
        $this->fakePagoAprobado('pago-ajeno-contrato', 8000, '999');
        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-ajeno-contrato',
        ])->assertStatus(403);

        $this->assertNotEmpty($response->json('error'));
        $this->assertSame('El pago no corresponde a este comercio', $response->json('error'));
    }

    /**
     * El 400 del pago no aprobado también tiene que traer el motivo: el
     * frontend lo muestra al usuario en vez de un texto genérico.
     *
     * Va en un test aparte porque `Http::fake` no reemplaza un registro previo
     * del mismo patrón: el primero sigue ganando.
     */
    public function test_el_400_de_pago_no_aprobado_trae_mensaje_legible(): void
    {
        $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        config(['services.mercadopago.access_token' => 'TEST-1234567890']);
        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pago-rechazado-contrato',
                'status' => 'rejected',
                'external_reference' => '1',
                'transaction_amount' => 8000,
            ]),
        ]);

        $this->actingAsAdminA();

        $response = $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-rechazado-contrato',
        ])->assertStatus(400);

        $this->assertNotEmpty($response->json('error'));
        $this->assertSame('El pago no está aprobado', $response->json('error'));
    }

    /**
     * El mismo payment_id no puede alargar el período dos veces, venga por el
     * endpoint HTTP o por el webhook.
     */
    public function test_confirmar_dos_veces_el_mismo_pago_no_extiende_dos_meses(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'suspendido',
            'vencimiento_pago' => now()->subDays(10)->toDateString(),
            'pending_plan_id' => 1,
        ]);

        $this->fakePagoAprobado('pago-idempotente');
        $this->actingAsAdminA();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-idempotente',
        ])->assertOk();

        $vencimientoTrasPrimerPago = Comercio::findOrFail(1)->vencimiento_pago->toDateString();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-idempotente',
        ])->assertOk();

        $comercio->refresh();
        $this->assertSame($vencimientoTrasPrimerPago, $comercio->vencimiento_pago->toDateString());
        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * El webhook y la confirmación HTTP deben aplicar la misma fórmula de
     * prórroga; si divergen, la fecha final depende del orden de llegada.
     */
    public function test_webhook_y_confirmacion_http_aplican_la_misma_prorroga(): void
    {
        $vencimientoInicial = now()->addDays(3)->toDateString();

        // Camino A: llega el webhook primero.
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'vencimiento_pago' => $vencimientoInicial,
            'pending_plan_id' => 1,
        ]);
        $this->fakePagoAprobado('pago-paridad-webhook');
        $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pago-paridad-webhook'],
        ])->assertOk();
        $porWebhook = Comercio::findOrFail(1)->vencimiento_pago->toDateString();

        // Camino B: gana la confirmación HTTP.
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'vencimiento_pago' => $vencimientoInicial,
            'pending_plan_id' => 1,
        ]);
        $this->fakePagoAprobado('pago-paridad-http');
        $this->actingAsAdminA();
        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-paridad-http',
        ])->assertOk();
        $porHttp = Comercio::findOrFail(1)->vencimiento_pago->toDateString();

        $this->assertSame(
            now()->addDays(3)->addMonthNoOverflow()->toDateString(),
            $porWebhook
        );
        $this->assertSame($porWebhook, $porHttp);
    }

    // ------------------------------------------------------------------
    // Estado de suscripción expuesto al frontend
    // ------------------------------------------------------------------

    public function test_mi_plan_expone_estado_por_vencer_con_vencimiento_visible(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/mi-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suscripcion/MiPlan')
                ->where('suscripcion.estado', 'por_vencer')
                ->where('suscripcion.dias_restantes', 5)
                ->where('suscripcion.puede_renovar', true)
                ->where('suscripcion.suspendido', false)
                ->where('suscripcion.vencimiento_pago', now()->addDays(5)->format('d/m/Y'))
            );
    }

    public function test_mi_plan_expone_estado_vencida_y_habilita_renovar(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'suspendido',
            'plan_id' => 1,
            'vencimiento_pago' => now()->subDays(2)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/mi-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suscripcion/MiPlan')
                ->where('suscripcion.estado', 'vencida')
                ->where('suscripcion.dias_restantes', -2)
                ->where('suscripcion.puede_renovar', true)
                ->where('suscripcion.suspendido', true)
            );
    }

    public function test_mi_plan_expone_estado_activa_sin_habilitar_renovar(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/mi-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suscripcion/MiPlan')
                ->where('suscripcion.estado', 'activa')
                ->where('suscripcion.puede_renovar', false)
            );
    }

    public function test_mi_plan_expone_estado_sin_vencimiento(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => null,
        ]);

        $this->actingAsAdminA();

        $this->get('/mi-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suscripcion/MiPlan')
                ->where('suscripcion.estado', 'sin_vencimiento')
                ->where('suscripcion.dias_restantes', null)
                ->where('suscripcion.puede_renovar', false)
            );
    }

    public function test_plan_actual_incluye_el_estado_para_el_polling(): void
    {
        $comercio = $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAsAdminA();

        $this->get('/api/mi-plan/plan-actual')
            ->assertOk()
            ->assertJsonPath('suscripcion.estado', 'por_vencer')
            ->assertJsonPath('suscripcion.dias_restantes', 2)
            ->assertJsonPath('suscripcion.puede_renovar', true);
    }

    // ------------------------------------------------------------------
    // Anti-duplicado de preferencias
    // ------------------------------------------------------------------

    public function test_no_se_genera_una_segunda_preferencia_con_un_pago_en_vuelo(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-1',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-1',
            ]),
        ]);

        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 1, 'origin' => 'http://localhost'])
            ->assertOk()
            ->assertJson(['es_renovacion' => true]);

        // El segundo intento, antes de que Mercado Pago confirme el primero,
        // se rechaza para no cobrar dos veces.
        $this->postJson('/mi-plan/pagar', ['plan_id' => 1, 'origin' => 'http://localhost'])
            ->assertStatus(409);

        Http::assertSentCount(1);
    }

    public function test_confirmar_el_pago_libera_el_bloqueo_de_pago_en_vuelo(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-1',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-1',
            ]),
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pago-ok',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 8000,
            ]),
        ]);

        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 1, 'origin' => 'http://localhost'])->assertOk();

        $this->postJson('/api/mi-plan/confirmar-upgrade', [
            'plan_id' => 1,
            'payment_id' => 'pago-ok',
        ])->assertOk();

        // Con el pago aplicado ya se puede generar la siguiente preferencia.
        $this->postJson('/mi-plan/pagar', ['plan_id' => 1, 'origin' => 'http://localhost'])->assertOk();
    }

    public function test_generar_preferencia_distingue_renovacion_de_cambio_de_plan(): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-2',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-2',
            ]),
        ]);

        $this->actingAsAdminA();

        // El plan actual del comercio 1 es el 1: renovar el mismo plan.
        $this->postJson('/mi-plan/pagar', ['plan_id' => 1, 'origin' => 'http://localhost'])
            ->assertOk()
            ->assertJson(['es_renovacion' => true, 'plan_id' => 1]);

        app(SuscripcionService::class)->limpiarPagoEnVuelo(1);

        // Un plan distinto es cambio de plan, no renovación.
        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])
            ->assertOk()
            ->assertJson(['es_renovacion' => false, 'plan_id' => 3]);
    }

    // ------------------------------------------------------------------
    // Ciclo de vida del pago en vuelo
    //
    // Cubre el reporte "la pantalla se quedó en 'Estamos procesando tu pago'
    // después de seleccionar un plan por error y nunca pagar": el pago queda
    // abierto en Mercado Pago, `pending_plan_id` apuntando al plan elegido y
    // el frontend sin forma de distinguir "pago pendiente real" de "el
    // usuario se fue sin pagar".
    // ------------------------------------------------------------------

    private function fakePreferencia(string $prefId = 'pref-vuelo'): void
    {
        config([
            'services.mercadopago.access_token' => 'TEST-1234567890',
            'services.mercadopago.public_url' => 'https://brandi-palmar-pickily.ngrok-free.dev',
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => $prefId,
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id='.$prefId,
            ]),
        ]);
    }

    /**
     * Escenario 1: preferencia creada + pago pendiente.
     */
    public function test_preferencia_creada_deja_el_pago_marcado_en_vuelo(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        // El backend reporta la preferencia viva: el frontend puede mostrar el
        // banner y deshabilitar los botones con datos reales, no con el
        // localStorage del navegador.
        $this->get('/api/mi-plan/plan-actual')
            ->assertOk()
            ->assertJsonPath('plan_id', 1)
            ->assertJsonPath('pending_plan_id', 3)
            ->assertJsonPath('suscripcion.pago_en_vuelo.plan_id', 3)
            ->assertJsonPath('suscripcion.pago_en_vuelo.expira_en_minutos', 30);

        $this->get('/mi-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('suscripcion.pago_en_vuelo.plan_id', 3)
            );
    }

    /**
     * Escenario 2: con el pago pendiente, el polling no debe considerar el
     * cambio aplicado ni tocar la fecha de vencimiento.
     */
    public function test_pago_pendiente_no_modifica_la_suscripcion(): void
    {
        $vencimientoOriginal = now()->addDays(30)->toDateString();

        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => $vencimientoOriginal,
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        // Tres ticks del polling: el plan sigue siendo el 1, hay pago en vuelo
        // y el vencimiento no se movió. El frontend no puede cerrar el polling
        // como "aplicado".
        for ($i = 0; $i < 3; $i++) {
            $this->get('/api/mi-plan/plan-actual')
                ->assertOk()
                ->assertJsonPath('plan_id', 1)
                ->assertJsonPath('pending_plan_id', 3)
                ->assertJsonPath('suscripcion.pago_en_vuelo.plan_id', 3);
        }

        $comercio = Comercio::findOrFail(1);
        $this->assertSame(1, (int) $comercio->plan_id);
        $this->assertSame($vencimientoOriginal, $comercio->vencimiento_pago->toDateString());
        $this->assertDatabaseMissing('payments', [
            'provider' => 'mercadopago',
        ]);
    }

    /**
     * Escenario 3: el usuario elige accidentalmente otro plan mientras ya hay
     * una preferencia en vuelo. El guard debe responder 409 y NO pisar
     * `pending_plan_id` con el plan nuevo.
     */
    public function test_elegir_otro_plan_con_preferencia_en_vuelo_da_409_sin_pisar_el_pendiente(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        // Primera intención: plan 3.
        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();
        $this->assertSame(3, (int) Comercio::findOrFail(1)->pending_plan_id);

        // El usuario se confunde y hace clic en otro plan (2). El guard lo frena.
        $this->postJson('/mi-plan/pagar', ['plan_id' => 2, 'origin' => 'http://localhost'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'Ya hay un pago en proceso para tu plan. Espera la confirmación de Mercado Pago antes de generar otro.');

        // Sólo se generó una preferencia y el pendiente sigue siendo el 3.
        Http::assertSentCount(1);
        $this->assertSame(3, (int) Comercio::findOrFail(1)->pending_plan_id);

        // Sigue siendo un pago en vuelo: el polling no puede declararlo liberado.
        $this->get('/api/mi-plan/plan-actual')
            ->assertJsonPath('suscripcion.pago_en_vuelo.plan_id', 3);
    }

    /**
     * Escenario 4: el pago se aprueba. El webhook aplica el cambio de plan,
     * extiende el vencimiento, limpia `pending_plan_id` y cierra el pago en
     * vuelo, de modo que el polling puede terminar.
     */
    public function test_webhook_aprobado_aplica_el_cambio_y_limpia_el_pendiente(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        // Ahora Mercado Pago notifica el pago aprobado.
        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-aprobado',
                'status' => 'approved',
                'external_reference' => '1',
                'transaction_amount' => 35000,
            ]),
        ]);

        $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-aprobado'],
        ])->assertOk()->assertJson(['status' => 'ok']);

        $comercio = Comercio::findOrFail(1);
        $this->assertSame(3, (int) $comercio->plan_id);
        $this->assertSame(now()->addDays(30)->addMonthNoOverflow()->toDateString(), $comercio->vencimiento_pago->toDateString());
        $this->assertNull($comercio->pending_plan_id);

        // El polling ahora ve la condición de éxito: plan aplicado + pendiente
        // limpio + sin pago en vuelo. Por eso puede dejar de mostrar el
        // banner y mostrar la confirmación.
        $this->get('/api/mi-plan/plan-actual')
            ->assertOk()
            ->assertJsonPath('plan_id', 3)
            ->assertJsonPath('pending_plan_id', null)
            ->assertJsonPath('suscripcion.pago_en_vuelo', null);

        $this->assertDatabaseHas('payments', [
            'gateway_transaction_id' => 'pay-aprobado',
            'status' => 'approved',
        ]);
    }

    /**
     * Escenario 5: pago rechazado / cancelado / expirado. No se aplica nada y
     * el estado pendiente se libera para que el usuario pueda reintentar.
     *
     * @dataProvider estadosTerminalesProveedor
     */
    public function test_webhook_con_pago_no_aprobado_no_aplica_y_libera_el_pendiente(string $estadoMp): void
    {
        $vencimientoOriginal = now()->addDays(30)->toDateString();

        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => $vencimientoOriginal,
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-fallido',
                'status' => $estadoMp,
                'external_reference' => '1',
                'transaction_amount' => 35000,
            ]),
        ]);

        $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-fallido'],
        ])->assertOk()->assertJson(['status' => 'not_approved']);

        // Nada se aplicó: el plan sigue siendo el 1 y la fecha no se movió.
        $comercio = Comercio::findOrFail(1);
        $this->assertSame(1, (int) $comercio->plan_id);
        $this->assertSame($vencimientoOriginal, $comercio->vencimiento_pago->toDateString());
        $this->assertDatabaseMissing('payments', ['gateway_transaction_id' => 'pay-fallido']);

        // El estado pendiente se liberó: `pending_plan_id` limpio y sin pago en
        // vuelo. Es la condición que el polling usa para pasar a "liberado".
        $this->assertNull($comercio->pending_plan_id);
        $this->get('/api/mi-plan/plan-actual')
            ->assertOk()
            ->assertJsonPath('pending_plan_id', null)
            ->assertJsonPath('suscripcion.pago_en_vuelo', null);

        // Y el usuario ya puede volver a intentar sin chocar con el 409.
        $this->fakePreferencia('pref-reintento');
        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();
        $this->assertSame(3, (int) Comercio::findOrFail(1)->pending_plan_id);
    }

    public static function estadosTerminalesProveedor(): array
    {
        return [
            'rechazado' => ['rejected'],
            'cancelado' => ['cancelled'],
            'expirado' => ['expired'],
        ];
    }

    /**
     * Un pago `pending` NO es terminal: puede completarse después, así que el
     * webhook no debe liberar nada.
     */
    public function test_webhook_con_pago_pendiente_no_libera_el_estado(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 'pay-pending',
                'status' => 'pending',
                'external_reference' => '1',
                'transaction_amount' => 35000,
            ]),
        ]);

        $this->postJson('/api/mercadopago/notificacion', [
            'tipo' => 'plan',
            'data' => ['id' => 'pay-pending'],
        ])->assertOk()->assertJson(['status' => 'not_approved']);

        $this->assertSame(3, (int) Comercio::findOrFail(1)->pending_plan_id);
        $this->get('/api/mi-plan/plan-actual')
            ->assertJsonPath('suscripcion.pago_en_vuelo.plan_id', 3);
    }

    /**
     * Escenario 5 (bis): el usuario abandona el checkout y libera el pago desde
     * la propia pantalla. Debe limpiar el pendiente y permitir reintentar.
     */
    public function test_cancelar_pago_desde_la_pantalla_libera_y_permite_reintentar(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        $this->postJson('/api/mi-plan/cancelar-pago')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suscripcion.pago_en_vuelo', null);

        $comercio = Comercio::findOrFail(1);
        $this->assertNull($comercio->pending_plan_id);
        $this->assertSame(1, (int) $comercio->plan_id);
        $this->assertSame('activo', $comercio->status);

        // Reintento inmediato: ya no hay 409.
        $this->fakePreferencia('pref-reintento');
        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'description' => 'plan_payment_released',
            'subject_id' => 1,
            'subject_type' => Comercio::class,
        ]);
    }

    /**
     * El estado inicial no debe inventar un pago en vuelo: sin preferencia
     * abierta, `pago_en_vuelo` es null y el frontend no muestra el banner.
     */
    public function test_sin_preferencia_no_hay_pago_en_vuelo(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
            'pending_plan_id' => null,
        ]);

        $this->actingAsAdminA();

        $this->get('/api/mi-plan/plan-actual')
            ->assertOk()
            ->assertJsonPath('pending_plan_id', null)
            ->assertJsonPath('suscripcion.pago_en_vuelo', null);
    }

    /**
     * La ventana de bloqueo se agota sola: pasada la hora, la preferencia
     * huérfana se limpia y el comercio puede volver a pagar sin esperar el TTL
     * completo ni intervención manual.
     */
    public function test_la_ventana_de_bloqueo_expira_sola(): void
    {
        $this->prepararComercio([
            'status' => 'activo',
            'plan_id' => 1,
            'vencimiento_pago' => now()->addDays(30)->toDateString(),
        ]);

        $this->fakePreferencia();
        $this->actingAsAdminA();

        $this->postJson('/mi-plan/pagar', ['plan_id' => 3, 'origin' => 'http://localhost'])->assertOk();

        $this->assertNotNull(
            app(SuscripcionService::class)->pagoEnVuelo(1),
            'La preferencia debería estar viva recién generada.'
        );

        $this->travel(31)->minutes();

        $this->assertNull(
            app(SuscripcionService::class)->pagoEnVuelo(1),
            'Pasada la ventana, la preferencia huérfana debe quedar liberada.'
        );

        $this->travelBack();
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
