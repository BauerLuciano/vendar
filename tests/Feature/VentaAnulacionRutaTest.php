<?php

namespace Tests\Feature;

use App\Models\DetalleVenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCaseMultiTenant;

/**
 * Contrato HTTP de la anulación y devolución de ventas.
 *
 * Se agregó porque la anulación se disparaba con `router.post` desde
 * `Pages/Ventas/Index.vue` mientras la ruta está registrada con `Route::patch`,
 * lo que producía "The POST method is not supported for route
 * ventas/{venta}/cancelar". El backend siempre usó PATCH y el otro caller
 * (`Components/ConfirmarPagoModal.vue`) ya enviaba PATCH.
 *
 * Estos tests fijan el verbo para que frontend y backend no vuelvan a
 * desalinearse, y cubren que la anulación siga siendo puramente comercial
 * (stock repuesto, venta cancelada) sin ninguna emisión fiscal.
 */
class VentaAnulacionRutaTest extends TestCaseMultiTenant
{
    /** El verbo de la ruta debe ser PATCH, que es lo que envían los callers. */
    public function test_la_ruta_de_anulacion_es_patch(): void
    {
        $this->assertContains(
            'PATCH',
            Route::getRoutes()->getByName('ventas.cancelar')->methods()
        );
    }

    /** La devolución es una operación distinta: se mantiene en POST. */
    public function test_la_ruta_de_devolucion_es_post(): void
    {
        $this->assertContains(
            'POST',
            Route::getRoutes()->getByName('ventas.devolver')->methods()
        );
    }

    /** Reproduce el error reportado: POST no debeaturar la ruta PATCH. */
    public function test_post_no_esta_soportado_en_la_ruta_de_anulacion(): void
    {
        $this->actingAsAdminA();

        $this->postJson('/ventas/1/cancelar', ['motivo' => 'verbo incorrecto'])
            ->assertStatus(405);
    }

    public function test_patch_anula_la_venta_y_repone_el_stock(): void
    {
        $this->actingAsAdminA();

        $response = $this->patch('/ventas/1/cancelar', ['motivo' => 'Error en la carga']);

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('ventas', [
            'id' => 1,
            'estado' => 'Cancelada',
            'motivo_anulacion' => 'Error en la carga',
        ]);

        // Toda la línea del detalle queda devuelta.
        $detalle = DetalleVenta::where('venta_id', 1)->first();
        $this->assertNotNull($detalle);
        $this->assertEquals((float) $detalle->cantidad, (float) $detalle->cantidad_devuelta);

        // Y la venta generó el movimiento de stock de cancelación.
        $sucursalId = DB::table('ventas')->where('id', 1)->value('turno_caja_id');
        $movimientos = DB::table('movimientos_stock')
            ->where('producto_id', $detalle->producto_id)
            ->where('tipo_movimiento', 'Cancelación Venta')
            ->where('motivo', 'like', '%Venta #1%')
            ->count();

        $this->assertGreaterThanOrEqual(1, $movimientos);
        $this->assertNotNull($sucursalId);
    }

    /** La anulación no debe emitir ni dejar rastros fiscales. */
    public function test_la_anulacion_no_toca_tablas_fiscales(): void
    {
        $this->actingAsAdminA();

        $this->patch('/ventas/1/cancelar', ['motivo' => 'Sin fiscalidad'])->assertRedirect();

        // Las tablas fiscales se conservan (no se dropearon) pero la anulación
        // comercial no debe escribir en ellas.
        foreach (['comprobantes_fiscales', 'nc_pendientes', 'certificados_fiscales'] as $tabla) {
            $existe = DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $tabla)
                ->exists();

            if ($existe) {
                $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} no debe recibir escrituras.");
            }
        }
    }

    /** Sin permiso "anular ventas" sigue rechazando. */
    public function test_sin_permiso_no_puede_anular(): void
    {
        $this->actingAsUserA();

        $this->patch('/ventas/1/cancelar', ['motivo' => 'sin permiso'])->assertForbidden();
    }
}