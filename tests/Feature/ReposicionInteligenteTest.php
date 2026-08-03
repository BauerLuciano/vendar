<?php

namespace Tests\Feature;

use App\Models\ReposicionSugerida;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

class ReposicionInteligenteTest extends TestCaseMultiTenant
{
    public function test_index_usa_stock_objetivo_como_cantidad(): void
    {
        $this->actingAsAdminA();

        // Producto 1: min 10, objetivo 20, stock 0 → aparece, sugerido 20.
        DB::table('producto_sucursal')->where('sucursal_id', 1)->where('producto_id', 1)
            ->update(['cantidad_fisica' => 0]);
        // Producto 2: min 15, objetivo 30, stock 28 → NO aparece (28 >= min).
        DB::table('producto_sucursal')->where('sucursal_id', 1)->where('producto_id', 2)
            ->update(['cantidad_fisica' => 28]);
        // Producto 3: min 10, objetivo 20, stock 100 → NO aparece (100 >= min).
        DB::table('producto_sucursal')->where('sucursal_id', 1)->where('producto_id', 3)
            ->update(['cantidad_fisica' => 100]);

        DB::table('productos')->where('id', 1)->update(['stock_objetivo' => 20]);
        DB::table('productos')->where('id', 2)->update(['stock_objetivo' => 30]);
        DB::table('productos')->where('id', 3)->update(['stock_objetivo' => 20]);

        $response = $this->get('/reposicion');
        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    public function test_sin_objetivo_no_aparece_en_lista(): void
    {
        $this->actingAsAdminA();

        // Producto 1: sin stock_objetivo, stock 0 < min 10 → NO debe estar en la lista,
        // debe contarse en el banner "sin objetivo".
        DB::table('producto_sucursal')->where('sucursal_id', 1)->where('producto_id', 1)
            ->update(['cantidad_fisica' => 0]);
        DB::table('productos')->where('id', 1)->update(['stock_objetivo' => null]);

        $response = $this->get('/reposicion');
        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    public function test_recordar_oculta_producto_por_hoy(): void
    {
        $this->actingAsAdminA();

        DB::table('producto_sucursal')->where('sucursal_id', 1)->where('producto_id', 1)
            ->update(['cantidad_fisica' => 0]);

        $response = $this->post('/reposicion/recordar', ['producto_id' => 1]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reposiciones_sugeridas', [
            'comercio_id' => 1,
            'sucursal_id' => 1,
            'producto_id' => 1,
            'estado' => 'ignorado',
        ]);

        $sugerida = ReposicionSugerida::where('sucursal_id', 1)->where('producto_id', 1)->first();
        $this->assertNotNull($sugerida->ignorado_hasta);
        $this->assertTrue($sugerida->ignorado_hasta->isToday());
    }

    public function test_recordar_solo_guarda_estado_no_cantidad(): void
    {
        $this->actingAsAdminA();

        $this->post('/reposicion/recordar', ['producto_id' => 1]);

        $row = DB::select('select * from reposiciones_sugeridas limit 1')[0];
        $columns = array_keys(get_object_vars($row));
        $this->assertNotContains('cantidad_sugerida', $columns);
        $this->assertNotContains('cantidad_fisica', $columns);
    }

    public function test_multi_tenant_aisla_sugerencias(): void
    {
        $this->actingAsAdminB();

        $response = $this->post('/reposicion/recordar', ['producto_id' => 11]);
        $response->assertRedirect();

        $this->assertDatabaseHas('reposiciones_sugeridas', [
            'comercio_id' => 2,
            'sucursal_id' => 3,
            'producto_id' => 11,
        ]);

        // El comercio 1 no debe ver la sugerencia del comercio 2
        $this->assertDatabaseMissing('reposiciones_sugeridas', [
            'comercio_id' => 1,
            'sucursal_id' => 1,
            'producto_id' => 11,
        ]);
    }
}
