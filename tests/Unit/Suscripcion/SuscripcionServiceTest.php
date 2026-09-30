<?php

namespace Tests\Unit\Suscripcion;

use App\Models\Comercio;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fórmulas puras del servicio de suscripción: no tocan la base de datos.
 */
class SuscripcionServiceTest extends TestCase
{
    private SuscripcionService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = new SuscripcionService();
    }

    // ------------------------------------------------------------------
    // nuevaFechaVencimiento
    // ------------------------------------------------------------------

    public function test_renovar_con_vencimiento_vigente_prorroga_desde_esa_fecha(): void
    {
        $hoy = Carbon::now();

        $nueva = $this->svc->nuevaFechaVencimiento($hoy->copy()->addDays(20));

        $this->assertSame(
            $hoy->copy()->addDays(20)->addMonthNoOverflow()->toDateString(),
            $nueva->toDateString()
        );
    }

    public function test_renovar_con_vencimiento_vencido_arranca_un_mes_desde_hoy(): void
    {
        $hoy = Carbon::now();

        $nueva = $this->svc->nuevaFechaVencimiento($hoy->copy()->subDays(5));

        $this->assertSame($hoy->copy()->addMonthNoOverflow()->toDateString(), $nueva->toDateString());
    }

    public function test_renovar_sin_vencimiento_arranca_un_mes_desde_hoy(): void
    {
        $hoy = Carbon::now();

        $nueva = $this->svc->nuevaFechaVencimiento(null);

        $this->assertSame($hoy->copy()->addMonthNoOverflow()->toDateString(), $nueva->toDateString());
    }

    public function test_renovar_el_dia_del_vencimiento_arranca_un_mes_desde_hoy(): void
    {
        $hoy = Carbon::now();

        // El vencimiento es hoy a las 00:00, así que ya no está "en el futuro":
        // la prórroga arranca un mes nuevo desde hoy, no desde esa medianoche.
        $nueva = $this->svc->nuevaFechaVencimiento($hoy->copy()->startOfDay());

        $this->assertSame($hoy->copy()->addMonthNoOverflow()->toDateString(), $nueva->toDateString());
    }

    public function test_prorroga_no_hace_drift_en_meses_de_31_dias(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00'));

        // 31/01 + 1 mes debe dar 28/02, no 02/03.
        $nueva = $this->svc->nuevaFechaVencimiento(Carbon::parse('2026-01-31 00:00:00'));

        $this->assertSame('2026-02-28', $nueva->toDateString());

        $this->travelBack();
    }

    public function test_prorroga_repetida_no_acumula_meses(): void
    {
        $this->travelTo(Carbon::parse('2026-05-10 10:00:00'));

        $primera = $this->svc->nuevaFechaVencimiento(Carbon::parse('2026-05-31 00:00:00'));
        $this->assertSame('2026-06-30', $primera->toDateString());

        // Un segundo intento sobre una fecha ya vencida reinicia desde hoy en
        // lugar de encadenar meses sobre meses.
        $segunda = $this->svc->nuevaFechaVencimiento(Carbon::parse('2026-01-31 00:00:00'));
        $this->assertSame('2026-06-10', $segunda->toDateString());

        $this->travelBack();
    }

    // ------------------------------------------------------------------
    // estadoSuscripcion
    // ------------------------------------------------------------------

    public function test_estado_activa_cuando_faltan_mas_de_diez_dias(): void
    {
        // 12 días: todavía "activa" (el banner del Dashboard avisa a los 10),
        // pero ya dentro de la ventana de renovación anticipada.
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(12));

        $this->assertSame('activa', $estado['estado']);
        $this->assertSame(12, $estado['dias_restantes']);
        $this->assertTrue($estado['puede_renovar']);
        $this->assertNull($estado['dias_para_renovar']);
    }

    public function test_estado_por_vencer_habilita_renovar(): void
    {
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(5));

        $this->assertSame('por_vencer', $estado['estado']);
        $this->assertSame(5, $estado['dias_restantes']);
        $this->assertTrue($estado['puede_renovar']);
    }

    public function test_estado_por_vencer_en_el_umbral_exacto_de_diez_dias(): void
    {
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(10));

        $this->assertSame('por_vencer', $estado['estado']);
        $this->assertTrue($estado['puede_renovar']);
    }

    public function test_estado_vencida_habilita_renovar(): void
    {
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(-3));

        $this->assertSame('vencida', $estado['estado']);
        $this->assertSame(-3, $estado['dias_restantes']);
        $this->assertTrue($estado['puede_renovar']);
    }

    public function test_el_dia_del_vencimiento_cuenta_como_por_vencer_y_no_como_vencida(): void
    {
        // El middleware de corte usa endOfDay(), así que la cuenta sigue
        // viva durante el día del vencimiento: el estado debe coincidir.
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(0));

        $this->assertSame('por_vencer', $estado['estado']);
        $this->assertSame(0, $estado['dias_restantes']);
        $this->assertTrue($estado['puede_renovar']);
    }

    public function test_cuenta_suspendida_con_vencimiento_lejano_habilita_renovar(): void
    {
        $comercio = $this->comercioVigente(120);
        $comercio->status = 'suspendido';

        $estado = $this->svc->estadoSuscripcion($comercio);

        $this->assertTrue($estado['suspendido']);
        $this->assertTrue($estado['puede_renovar']);
    }

    public function test_sin_vencimiento_no_habilita_renovar_si_esta_activo(): void
    {
        $comercio = $this->comercioVigente(30);
        $comercio->vencimiento_pago = null;

        $estado = $this->svc->estadoSuscripcion($comercio);

        $this->assertSame('sin_vencimiento', $estado['estado']);
        $this->assertNull($estado['dias_restantes']);
        $this->assertFalse($estado['puede_renovar']);
    }

    public function test_sin_vencimiento_pero_suspendido_habilita_renovar(): void
    {
        $comercio = $this->comercioVigente(30);
        $comercio->vencimiento_pago = null;
        $comercio->status = 'suspendido';

        $estado = $this->svc->estadoSuscripcion($comercio);

        $this->assertSame('sin_vencimiento', $estado['estado']);
        $this->assertTrue($estado['puede_renovar']);
    }

    public function test_comercio_inexistente_devuelve_estado_vacio(): void
    {
        $estado = $this->svc->estadoSuscripcion(null);

        $this->assertSame('sin_vencimiento', $estado['estado']);
        $this->assertNull($estado['plan_id']);
        $this->assertFalse($estado['puede_renovar']);
    }

    // ------------------------------------------------------------------
    // Ventana de renovación anticipada (SuscripcionService::VENTANA_RENOVACION_DIAS)
    // ------------------------------------------------------------------

    public function test_fuera_de_la_ventana_no_habilita_renovar_y_cuenta_los_dias(): void
    {
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(45));

        $this->assertSame('activa', $estado['estado']);
        $this->assertFalse($estado['puede_renovar']);
        $this->assertSame(45, $estado['dias_restantes']);
        // 45 - 30 = 15 días hasta que se abra la ventana.
        $this->assertSame(15, $estado['dias_para_renovar']);
    }

    public function test_la_ventana_de_renovacion_es_de_treinta_dias(): void
    {
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(60));

        $this->assertSame(30, $estado['ventana_renovacion_dias']);
        $this->assertSame(30, SuscripcionService::VENTANA_RENOVACION_DIAS);
    }

    public function test_en_el_limite_exacto_de_la_ventana_si_puede_renovar(): void
    {
        // El umbral es inclusivo: con exactamente 30 días restantes ya entra
        // en la ventana ("faltan 30 días o menos").
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(30));

        $this->assertSame(30, $estado['dias_restantes']);
        $this->assertTrue($estado['puede_renovar']);
        $this->assertNull($estado['dias_para_renovar']);
    }

    public function test_un_dia_por_encima_de_la_ventana_bloquea_la_renovacion(): void
    {
        $estado = $this->svc->estadoSuscripcion($this->comercioVigente(31));

        $this->assertFalse($estado['puede_renovar']);
        $this->assertSame(1, $estado['dias_para_renovar']);
    }

    public function test_cuenta_suspendida_con_vencimiento_lejano_puede_renovar_aunque_no_haya_ventana(): void
    {
        $comercio = $this->comercioVigente(200);
        $comercio->status = 'suspendido';

        $estado = $this->svc->estadoSuscripcion($comercio);

        $this->assertTrue($estado['puede_renovar']);
        $this->assertNull($estado['dias_para_renovar']);
        $this->assertTrue($this->svc->puedeRenovarAhora($comercio));
    }

    public function test_renovacion_anticipada_bloqueada_antes_de_la_ventana(): void
    {
        $this->assertFalse($this->svc->puedeRenovarAhora($this->comercioVigente(45)));
        $this->assertFalse($this->svc->puedeRenovarAhora($this->comercioVigente(31)));
    }

    public function test_renovacion_permitida_dentro_y_al_vencer(): void
    {
        $this->assertTrue($this->svc->puedeRenovarAhora($this->comercioVigente(30)));
        $this->assertTrue($this->svc->puedeRenovarAhora($this->comercioVigente(0)));
        $this->assertTrue($this->svc->puedeRenovarAhora($this->comercioVigente(-5)));
    }

    public function test_renovacion_bloqueada_sin_vencimiento_y_cuenta_activa(): void
    {
        $comercio = $this->comercioVigente(10);
        $comercio->vencimiento_pago = null;

        $this->assertFalse($this->svc->puedeRenovarAhora($comercio));
    }

    public function test_renovacion_bloqueada_si_no_hay_comercio(): void
    {
        $this->assertFalse($this->svc->puedeRenovarAhora(null));
    }

    public function test_el_estado_vacio_informa_la_ventana(): void
    {
        $estado = $this->svc->estadoSuscripcion(null);

        $this->assertSame(30, $estado['ventana_renovacion_dias']);
        $this->assertNull($estado['dias_para_renovar']);
        $this->assertFalse($estado['puede_renovar']);
    }

    private function comercioVigente(int $dias): Comercio
    {
        $comercio = new Comercio([
            'vencimiento_pago' => now()->startOfDay()->addDays($dias)->toDateString(),
        ]);
        $comercio->status = 'activo';
        $comercio->plan_id = 1;

        return $comercio;
    }
}
