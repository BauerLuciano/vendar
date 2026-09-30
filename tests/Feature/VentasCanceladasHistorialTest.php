<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\VentaStatus;
use App\Models\Caja;
use App\Models\Consumidor;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

/**
 * Historial propio de ventas canceladas: pantalla `/ventas/canceladas`.
 *
 * Verifica que sea una consulta independiente del listado general (no un filtro),
 * que la paginación venga del backend con 7 filas por página, que muestre el
 * motivo real de cada venta y que no rompa el historial normal.
 */
class VentasCanceladasHistorialTest extends TestCaseMultiTenant
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdminA();
    }

    // ------------------------------------------------------------------
    // Consulta independiente
    // ------------------------------------------------------------------

    public function test_la_pantalla_de_canceladas_devuelve_200_y_solo_ventas_canceladas(): void
    {
        $this->ventasDeEjemplo();

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ventas/Canceladas')
                ->has('ventas.data', 2)
            );
    }

    public function test_la_venta_completada_no_aparece_en_la_pantalla_de_canceladas(): void
    {
        $completada = $this->completada('Venta normal');
        $this->cancelada('Venta anulada');

        $datos = $this->filasCanceladas();

        $ids = array_column($datos, 'id');
        $this->assertNotContains($completada->id, $ids);
    }

    public function test_no_toma_ventas_canceladas_de_otra_sucursal(): void
    {
        $propia = $this->cancelada('Anulada de mi sucursal');

        $turnoAjeno = TurnoCaja::whereHas('caja', fn ($q) => $q->where('sucursal_id', 3))->firstOrFail();
        $ventaAjena = Venta::create([
            'turno_caja_id' => $turnoAjeno->id,
            'metodo_pago' => MetodoPago::EFECTIVO->value,
            'pagos' => [['metodo_pago' => MetodoPago::EFECTIVO->value, 'monto' => 500]],
            'total' => 500,
            'estado' => VentaStatus::CANCELLED,
            'motivo_anulacion' => 'Ajena',
        ]);

        $datos = $this->filasCanceladas();
        $ids = array_column($datos, 'id');

        $this->assertContains($propia->id, $ids);
        $this->assertNotContains($ventaAjena->id, $ids);
    }

    // ------------------------------------------------------------------
    // Paginación real de 7 filas
    // ------------------------------------------------------------------

    public function test_pagina_de_a_siete_registros_con_total_real(): void
    {
        $this->canceladasEnLote(16);

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('ventas.data', 7)
                ->has('ventas.links')
                ->where('ventas.total', 16)
                ->where('ventas.last_page', 3)
                ->where('ventas.current_page', 1)
            );
    }

    public function test_segunda_pagina_muestra_las_siguientes_sin_repetir(): void
    {
        $this->canceladasEnLote(16);

        $primera = $this->idsDePagina(1);
        $segunda = $this->idsDePagina(2);

        $this->assertCount(7, $primera);
        $this->assertCount(7, $segunda);
        $this->assertEmpty(array_intersect($primera, $segunda), 'La página 2 repitió registros de la página 1');
    }

    public function test_la_paginacion_recorre_todas_las_paginas_sin_perder_ni_repetir(): void
    {
        $this->canceladasEnLote(16);

        $vistos = [];
        for ($pagina = 1; $pagina <= 3; $pagina++) {
            $vistos = array_merge($vistos, $this->idsDePagina($pagina));
        }

        $this->assertCount(16, $vistos, 'La paginación perdió o repitió registros');
        $this->assertCount(16, array_unique($vistos));
    }

    public function test_la_ultima_pagina_trae_solo_los_restantes(): void
    {
        $this->canceladasEnLote(16);

        $this->assertCount(2, $this->idsDePagina(3));
    }

    // ------------------------------------------------------------------
    // Datos que muestra cada fila
    // ------------------------------------------------------------------

    public function test_muestra_el_motivo_real_guardado_en_la_venta(): void
    {
        $this->cancelada('Duele la panza');

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('ventas.data.0.motivo', 'Duele la panza'));
    }

    public function test_una_venta_sin_cliente_se_muestra_como_consumidor_final_y_no_como_guion(): void
    {
        $this->cancelada('Error de carga');

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ventas.data.0.cliente', 'Consumidor Final')
                ->where('ventas.data.0.es_consumidor_final', true)
            );
    }

    public function test_el_consumidor_final_registrado_asi_tambien_se_muestra_como_consumidor_final(): void
    {
        // El seeder QA crea el consumidor genérico con nombre "Consumidor" y
        // apellido "Final": sin corregir, el listado muestra "Consumidor".
        $generico = Consumidor::where('documento', '00000000')->firstOrFail();

        $this->cancelada('Cliente desistió', [
            'consumidor_id' => $generico->id,
        ]);

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ventas.data.0.cliente', 'Consumidor Final')
                ->where('ventas.data.0.es_consumidor_final', true)
            );
    }

    public function test_un_cliente_identificado_se_muestra_con_nombre_y_apellido(): void
    {
        $this->cancelada('Producto devuelto', ['consumidor_id' => $this->consumidorA->id]);

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ventas.data.0.cliente', trim($this->consumidorA->nombre.' '.$this->consumidorA->apellido))
                ->where('ventas.data.0.es_consumidor_final', false)
                ->where('ventas.data.0.documento', $this->consumidorA->documento)
            );
    }

    public function test_una_venta_a_cuenta_corriente_se_marca_como_tal(): void
    {
        $this->cancelada('Se frei', [
            'consumidor_id' => $this->consumidorA->id,
            'metodo_pago' => MetodoPago::CUENTA_CORRIENTE->value,
            'pagos' => [['metodo_pago' => MetodoPago::CUENTA_CORRIENTE->value, 'monto' => 800]],
        ]);

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ventas.data.0.es_cuenta_corriente', true)
                ->where('ventas.data.0.cliente', trim($this->consumidorA->nombre.' '.$this->consumidorA->apellido))
            );
    }

    public function test_muestra_total_metodo_de_pago_y_fecha_de_la_venta(): void
    {
        $this->cancelada('Falsa alarma', [
            'total' => 1600,
            'metodo_pago' => MetodoPago::EFECTIVO->value,
            'pagos' => [['metodo_pago' => MetodoPago::EFECTIVO->value, 'monto' => 1600]],
        ]);

        $fila = $this->filasCanceladas()[0];

        $this->assertSame(1600.0, (float) $fila['total']);
        $this->assertNotEmpty($fila['metodo_pago']);
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4}/', $fila['fecha_venta']);
    }

    // ------------------------------------------------------------------
    // Trazabilidad: quién y cuándo anuló
    // ------------------------------------------------------------------

    public function test_lee_quien_y_cuando_anonulo_desde_el_log_de_auditoria(): void
    {
        $venta = $this->cancelada('Anulada por Luciano');

        // Reproduce el evento que escribe la anulación real al cambiar el estado.
        activity()
            ->performedOn($venta)
            ->causedBy($this->adminA)
            ->event('updated')
            ->withProperties([
                'old' => ['estado' => VentaStatus::COMPLETED->value],
                'attributes' => [
                    'estado' => VentaStatus::CANCELLED->value,
                    'motivo_anulacion' => 'Anulada por Luciano',
                ],
            ])
            ->log('Venta anulada');

        $fila = $this->filasCanceladas()[0];

        $this->assertSame($this->adminA->name, $fila['cancelada_por']);
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/', $fila['cancelada_at']);
    }

    public function test_sin_evento_de_auditoria_no_inventa_fecha_ni_usuario(): void
    {
        $this->cancelada('Legacy previo al log');

        $fila = $this->filasCanceladas()[0];

        $this->assertNull($fila['cancelada_at']);
        $this->assertNull($fila['cancelada_por']);
        // El motivo, en cambio, siempre vive en la venta.
        $this->assertSame('Legacy previo al log', $fila['motivo']);
    }

    // ------------------------------------------------------------------
    // No rompe el historial general
    // ------------------------------------------------------------------

    public function test_la_venta_cancelada_sigue_apareciendo_en_el_historial_general(): void
    {
        $this->ventasDeEjemplo();

        $this->get('/ventas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Ventas/Index'));
    }

    public function test_el_filtro_de_estado_del_historial_general_sigue_funcionando(): void
    {
        $cancelada = $this->cancelada('Se rompe el paquete');
        $this->completada('Venta normal');

        $ids = $this->idsVentasGenerales('/ventas?estado=Cancelada');

        $this->assertContains($cancelada->id, $ids);
    }

    public function test_sin_ventas_canceladas_la_pantalla_no_falla(): void
    {
        $this->completada('Venta normal');

        $this->get('/ventas/canceladas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('ventas.data', 0)
                ->where('ventas.total', 0)
            );
    }

    // ------------------------------------------------------------------
    // La consulta es de sólo lectura
    // ------------------------------------------------------------------

    public function test_la_consulta_de_canceladas_no_modifica_nada(): void
    {
        $this->cancelada('Motivo original');

        $antes = DB::table('ventas')->where('estado', VentaStatus::CANCELLED->value)->get()->toArray();
        $movimientosAntes = DB::table('movimientos_stock')->count();

        $this->get('/ventas/canceladas')->assertOk();

        $despues = DB::table('ventas')->where('estado', VentaStatus::CANCELLED->value)->get()->toArray();

        $this->assertEquals($antes, $despues);
        $this->assertSame($movimientosAntes, DB::table('movimientos_stock')->count());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array<int, int> */
    private function idsDePagina(int $pagina): array
    {
        return array_column($this->filasCanceladas("/ventas/canceladas?page={$pagina}"), 'id');
    }

    /**
     * Props reales de la página de Inertia.
     *
     * No se puede usar `getJson()`: estas rutas devuelven la página de Inertia,
     * no JSON, salvo que se mande el header `X-Inertia`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function filasCanceladas(string $url = '/ventas/canceladas'): array
    {
        $props = null;

        $this->get($url)
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray();
            });

        return $props['ventas']['data'] ?? [];
    }

    /** @return array<int, int> */
    private function idsVentasGenerales(string $url): array
    {
        $props = null;

        $this->get($url)
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray();
            });

        return array_column($props['ventas']['data'] ?? [], 'id');
    }

    private function ventasDeEjemplo(): void
    {
        $this->completada('Venta normal 1');
        $this->cancelada('Anulada 1');
        $this->completada('Venta normal 2');
        $this->cancelada('Anulada 2');
    }

    private function canceladasEnLote(int $cuantas): void
    {
        for ($i = 1; $i <= $cuantas; $i++) {
            $this->cancelada("Anulada {$i}");
        }
    }

    private function turnoDeSucursal1(): TurnoCaja
    {
        return TurnoCaja::whereHas('caja', fn ($q) => $q->where('sucursal_id', 1))->firstOrFail();
    }

    private function completada(string $motivo = 'Venta', array $extra = []): Venta
    {
        return $this->crearVenta(VentaStatus::COMPLETED, $motivo, $extra);
    }

    private function cancelada(string $motivo, array $extra = []): Venta
    {
        return $this->crearVenta(VentaStatus::CANCELLED, $motivo, $extra);
    }

    private function crearVenta(VentaStatus $estado, string $motivo, array $extra = []): Venta
    {
        $metodo = $extra['metodo_pago'] ?? MetodoPago::EFECTIVO->value;
        $total = $extra['total'] ?? 1000;

        return Venta::create([
            'turno_caja_id' => $this->turnoDeSucursal1()->id,
            'consumidor_id' => $extra['consumidor_id'] ?? null,
            'metodo_pago' => $metodo,
            'pagos' => $extra['pagos'] ?? [['metodo_pago' => $metodo, 'monto' => $total]],
            'total' => $total,
            'estado' => $estado,
            'motivo_anulacion' => $motivo,
        ]);
    }
}
