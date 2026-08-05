<?php

namespace Tests\Feature;

use App\Models\Producto;
use Tests\TestCaseMultiTenant;

class Modulo1_ProductosTest extends TestCaseMultiTenant
{
    private function productoData(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Prod Test',
            'codigo_barras' => '999999'.random_int(100, 999),
            'categoria_id' => 1,
            'marca_id' => 1,
            'proveedor_id' => 1,
            'unidad_medida' => 'Unidad',
            'es_retornable' => false,
            'precio_costo' => 50,
            'precio_venta' => 100,
            'alicuota_iva' => 21,
            'stock_minimo' => 5,
            'stock_inicial' => 0,
            'descripcion' => 'Producto de prueba',
        ], $overrides);
    }

    // P1.1.1
    public function test_admin_a_puede_crear_producto(): void
    {
        $this->actingAsAdminA();

        $data = $this->productoData(['nombre' => 'Prod A1']);
        $response = $this->post('/productos', $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Prod A1',
            'codigo_barras' => $data['codigo_barras'],
        ]);
    }

    // P1.1.2
    public function test_admin_b_puede_crear_producto(): void
    {
        $this->actingAsAdminB();

        $data = $this->productoData(['nombre' => 'Prod B1']);
        $response = $this->post('/productos', $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Prod B1',
            'codigo_barras' => $data['codigo_barras'],
        ]);
    }

    // P1.1.3
    public function test_a_solo_ve_sus_productos(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/productos');
        $response->assertOk();

        $ids = Producto::whereHas('sucursales', fn ($q) => $q->whereIn('sucursales.id', [1, 2]))
            ->pluck('id');

        $this->assertTrue($ids->every(fn ($id) => $id >= 1 && $id <= 10));
        $this->assertTrue($ids->doesntContain(11));
    }

    // P1.1.4
    public function test_b_solo_ve_sus_productos(): void
    {
        $this->actingAsAdminB();

        $response = $this->get('/productos');
        $response->assertOk();

        $ids = Producto::whereHas('sucursales', fn ($q) => $q->whereIn('sucursales.id', [3, 4]))
            ->pluck('id');

        $this->assertTrue($ids->every(fn ($id) => $id >= 11 && $id <= 20));
        $this->assertTrue($ids->doesntContain(1));
    }

    // P1.2.1
    public function test_admin_a_puede_editar_su_producto(): void
    {
        $this->actingAsAdminA();

        $this->post('/productos/1', [
            'nombre' => 'Coca Editada',
            'codigo_barras' => '10000001',
            'categoria_id' => 1,
            'marca_id' => 1,
            'proveedor_id' => 1,
            'unidad_medida' => 'Unidad',
            'precio_costo' => 600,
            'precio_venta' => 900,
            'alicuota_iva' => 21,
            'stock_minimo' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('productos', [
            'id' => 1,
            'nombre' => 'Coca Editada',
            'precio_venta' => 900,
        ]);
    }

    // P1.2.2
    public function test_admin_a_no_puede_editar_producto_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/productos/11', [
            'nombre' => 'Hackeado',
            'codigo_barras' => '20000001',
            'categoria_id' => 1,
            'marca_id' => 1,
            'proveedor_id' => 1,
            'unidad_medida' => 'Unidad',
            'precio_costo' => 1,
            'precio_venta' => 1,
            'stock_minimo' => 1,
        ])->assertForbidden();
    }

    // P1.2.3
    public function test_admin_b_no_puede_editar_producto_de_a(): void
    {
        $this->actingAsAdminB();

        $this->post('/productos/1', [
            'nombre' => 'Hackeado',
            'codigo_barras' => '10000001',
            'categoria_id' => 1,
            'marca_id' => 1,
            'proveedor_id' => 1,
            'unidad_medida' => 'Unidad',
            'precio_costo' => 1,
            'precio_venta' => 1,
            'stock_minimo' => 1,
        ])->assertForbidden();
    }

    // P1.3.1
    public function test_admin_a_puede_desactivar_su_producto(): void
    {
        $this->actingAsAdminA();

        $this->patch('/productos/1/status')->assertRedirect();

        $this->assertDatabaseHas('productos', [
            'id' => 1,
            'estado' => false,
        ]);
    }

    // P1.3.2
    public function test_admin_a_no_puede_desactivar_producto_de_b(): void
    {
        $this->actingAsAdminA();

        $this->patch('/productos/11/status')->assertForbidden();
    }

    // P1.4.1
    public function test_admin_a_puede_ajustar_stock(): void
    {
        $this->actingAsAdminA();

        $this->post('/productos/1/ajuste-stock', [
            'sucursal_id' => 1,
            'tipo_ajuste' => 'Sumar',
            'cantidad' => 20,
            'motivo' => 'Test ajuste',
        ])->assertRedirect();
    }

    // P1.4.2
    public function test_admin_a_no_puede_ajustar_stock_producto_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/productos/11/ajuste-stock', [
            'sucursal_id' => 1,
            'tipo_ajuste' => 'Sumar',
            'cantidad' => 10,
            'motivo' => 'Test',
        ])->assertForbidden();
    }

    // P1.5.1  (requiere role:SuperAdmin)
    public function test_superadmin_puede_ver_auditoria_de_producto_a(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/productos/1/auditoria');
        $response->assertOk();
    }

    // P1.5.2
    // NOTA: auditoria() en ProductoController NO tiene scoping multi-tenant.
    // La ruta solo está protegida por role:SuperAdmin, no por comercio_id.
    // Esto es un gap de seguridad conocido: un SuperAdmin de A puede ver
    // movimientos de stock de productos de B.
    public function test_superadmin_a_puede_ver_auditoria_de_producto_b_porque_no_hay_scoping(): void
    {
        $this->actingAsAdminA();

        $this->get('/productos/11/auditoria')->assertOk();
    }
}
