<?php

namespace Tests\Feature;

use App\Models\Caja;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCaseMultiTenant;

class BuildB2_CajasUnicidadTest extends TestCaseMultiTenant
{
    private function payload(int $sucursalId, string $nombre): array
    {
        return [
            'sucursal_id' => $sucursalId,
            'nombre' => $nombre,
        ];
    }

    private function crearCaja(int $sucursalId, string $nombre): Caja
    {
        return Caja::create([
            'sucursal_id' => $sucursalId,
            'nombre' => $nombre,
        ]);
    }

    // ============================================================
    // UNICIDAD POR LOCAL
    // ============================================================

    public function test_permite_mismo_nombre_en_locales_diferentes(): void
    {
        $this->actingAsAdminA();

        $this->post('/cajas', $this->payload(1, 'Caja Compartida'))->assertRedirect();
        $this->post('/cajas', $this->payload(2, 'Caja Compartida'))->assertRedirect();

        $this->assertDatabaseHas('cajas', [
            'sucursal_id' => 1,
            'nombre' => 'Caja Compartida',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('cajas', [
            'sucursal_id' => 2,
            'nombre' => 'Caja Compartida',
            'deleted_at' => null,
        ]);
    }

    public function test_rechaza_mismo_nombre_en_el_mismo_local(): void
    {
        $this->actingAsAdminA();

        $this->post('/cajas', $this->payload(1, 'Caja Duplicada'))->assertRedirect();

        $this->post('/cajas', $this->payload(1, 'Caja Duplicada'))
            ->assertSessionHasErrors('nombre');

        $this->assertSame(
            1,
            Caja::where('sucursal_id', 1)->where('nombre', 'Caja Duplicada')->count()
        );
    }

    public function test_rechaza_nombre_con_diferencias_de_mayusculas(): void
    {
        $this->actingAsAdminA();

        $this->post('/cajas', $this->payload(1, 'Caja Unica Test'))->assertRedirect();

        $this->post('/cajas', $this->payload(1, 'caja unica test'))
            ->assertSessionHasErrors('nombre');

        $this->post('/cajas', $this->payload(1, 'CAJA UNICA TEST'))
            ->assertSessionHasErrors('nombre');
    }

    public function test_rechaza_nombre_con_espacios_sobrantes_equivalentes(): void
    {
        $this->actingAsAdminA();

        $this->post('/cajas', $this->payload(1, 'Caja con Espacios'))->assertRedirect();

        $this->post('/cajas', $this->payload(1, '  Caja con Espacios  '))
            ->assertSessionHasErrors('nombre');

        $this->assertSame(
            1,
            Caja::where('sucursal_id', 1)->where('nombre', 'Caja con Espacios')->count()
        );
    }

    // ============================================================
    // EDICIÓN
    // ============================================================

    public function test_editar_conservando_el_propio_nombre_es_permitido(): void
    {
        $this->actingAsAdminA();

        $this->put('/cajas/1', $this->payload(1, 'Caja Principal A'))
            ->assertRedirect();

        $this->assertDatabaseHas('cajas', [
            'id' => 1,
            'sucursal_id' => 1,
            'nombre' => 'Caja Principal A',
        ]);
    }

    public function test_rechaza_editar_con_el_nombre_de_otra_caja_activa_del_mismo_local(): void
    {
        $this->actingAsAdminA();

        $this->put('/cajas/1', $this->payload(1, 'Caja Kiosco Ventana A'))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseHas('cajas', [
            'id' => 1,
            'nombre' => 'Caja Principal A',
        ]);
    }

    public function test_permite_mover_una_caja_entre_locales_del_mismo_comercio(): void
    {
        $this->actingAsAdminA();

        $this->put('/cajas/1', $this->payload(2, 'Caja Movida'))
            ->assertRedirect();

        $this->assertDatabaseHas('cajas', [
            'id' => 1,
            'sucursal_id' => 2,
            'nombre' => 'Caja Movida',
        ]);
    }

    // ============================================================
    // SOFT DELETES
    // ============================================================

    public function test_permite_reutilizar_el_nombre_de_una_caja_eliminada_logicamente(): void
    {
        $this->actingAsAdminA();

        $caja = $this->crearCaja(1, 'Caja Reutilizable');
        $caja->delete();

        $this->assertSoftDeleted('cajas', ['id' => $caja->id]);
        $this->assertNotNull(Caja::withTrashed()->find($caja->id));

        $this->post('/cajas', $this->payload(1, 'Caja Reutilizable'))
            ->assertRedirect();

        $this->assertSame(
            1,
            Caja::where('sucursal_id', 1)
                ->where('nombre', 'Caja Reutilizable')
                ->whereNull('deleted_at')
                ->count()
        );

        $this->assertSame(
            2,
            Caja::withTrashed()
                ->where('sucursal_id', 1)
                ->where('nombre', 'Caja Reutilizable')
                ->count()
        );
    }

    // ============================================================
    // AISLAMIENTO MULTI-TENANT
    // ============================================================

    public function test_rechaza_operar_sobre_una_caja_de_otro_comercio(): void
    {
        $this->actingAsAdminA();

        $this->put('/cajas/4', $this->payload(3, 'Caja Hackeada'))
            ->assertForbidden();

        $this->patch('/cajas/4/status')->assertForbidden();
        $this->delete('/cajas/4')->assertForbidden();

        $this->assertDatabaseHas('cajas', [
            'id' => 4,
            'sucursal_id' => 3,
            'nombre' => 'Caja Principal B',
        ]);
    }

    public function test_rechaza_manipular_sucursal_id_hacia_otro_comercio(): void
    {
        $this->actingAsAdminA();

        // Alta apuntando a una sucursal del comercio B.
        $this->post('/cajas', $this->payload(3, 'Caja Cruzada'))
            ->assertSessionHasErrors('sucursal_id');

        // Edición intentando mover una caja del comercio A a una sucursal del comercio B.
        $this->put('/cajas/1', $this->payload(3, 'Caja Principal A'))
            ->assertSessionHasErrors('sucursal_id');

        $this->assertDatabaseHas('cajas', [
            'id' => 1,
            'sucursal_id' => 1,
            'nombre' => 'Caja Principal A',
        ]);
    }

    public function test_superadmin_solo_lista_cajas_de_su_comercio(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/cajas');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cajas/Index')
            ->has('cajas', 3)
        );

        $nombres = collect($response->viewData('page')['props']['cajas'])
            ->pluck('nombre')
            ->all();

        $this->assertContains('Caja Principal A', $nombres);
        $this->assertContains('Caja Kiosco Ventana A', $nombres);
        $this->assertContains('Caja Secundaria A', $nombres);
        $this->assertNotContains('Caja Principal B', $nombres);
        $this->assertNotContains('Caja Secundaria B', $nombres);
    }

    // ============================================================
    // RESTRICCIÓN A NIVEL POSTGRESQL
    // ============================================================

    public function test_postgresql_rechaza_un_duplicado_insertado_directamente(): void
    {
        DB::beginTransaction();

        try {
            DB::table('cajas')->insert([
                'sucursal_id' => 1,
                'nombre' => '  caja principal a  ',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('PostgreSQL permitió un duplicado que debía rechazar.');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->getCode());
        } finally {
            DB::rollBack();
        }
    }

    // ============================================================
    // HISTÓRICOS
    // ============================================================

    public function test_las_cajas_historicas_siguen_siendo_consultables(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/cajas');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cajas/Index')
            ->has('cajas', 3)
        );

        $nombres = collect($response->viewData('page')['props']['cajas'])
            ->pluck('nombre')
            ->all();

        $this->assertContains('Caja Principal A', $nombres);
        $this->assertContains('Caja Secundaria A', $nombres);
    }
}
