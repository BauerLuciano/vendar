<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Proveedor;
use Tests\TestCaseMultiTenant;

class MultiTenantIsolationTest extends TestCaseMultiTenant
{
    // =============================================
    // CATEGORIAS
    // =============================================

    public function test_categoria_usa_solo_ve_registros_de_su_comercio_y_globales(): void
    {
        Categoria::create(['nombreCategoria' => 'Bebidas A', 'slug' => 'bebidas-a', 'comercio_id' => 1, 'estado' => true]);
        Categoria::create(['nombreCategoria' => 'Bebidas B', 'slug' => 'bebidas-b', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminA();
        $response = $this->get('/categorias');

        $response->assertOk();
        $response->assertSee('General');
        $response->assertSee('Bebidas A');
        $response->assertDontSee('Bebidas B');
    }

    public function test_categoria_usuario_no_puede_editar_categoria_de_otro_comercio(): void
    {
        $catB = Categoria::create(['nombreCategoria' => 'Lácteos B', 'slug' => 'lacteos-b', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminA();
        $this->put('/categorias/' . $catB->id, [
            'nombreCategoria' => 'Hackeado',
        ])->assertForbidden();
    }

    public function test_categoria_usuario_no_puede_editar_categoria_global(): void
    {
        $this->actingAsAdminA();
        $this->put('/categorias/1', [
            'nombreCategoria' => 'Editado Global',
        ])->assertForbidden();
    }

    public function test_categoria_usuario_no_puede_eliminar_categoria_global(): void
    {
        $this->actingAsAdminA();
        $this->delete('/categorias/1')->assertForbidden();
    }

    public function test_categoria_usuario_no_puede_cambiar_status_categoria_de_otro_comercio(): void
    {
        $catB = Categoria::create(['nombreCategoria' => 'Snacks B', 'slug' => 'snacks-b', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminA();
        $this->patch('/categorias/' . $catB->id . '/status')->assertForbidden();
    }

    public function test_categoria_usuario_puede_editar_su_propia_categoria(): void
    {
        $catA = Categoria::create(['nombreCategoria' => 'Mi Categoria', 'slug' => 'mi-categoria', 'comercio_id' => 1, 'estado' => true]);

        $this->actingAsAdminA();
        $this->put('/categorias/' . $catA->id, [
            'nombreCategoria' => 'Mi Categoria Editada',
        ])->assertSessionHas('success');
    }

    // =============================================
    // MARCAS
    // =============================================

    public function test_marca_usa_solo_ve_registros_de_su_comercio_y_globales(): void
    {
        Marca::create(['nombreMarca' => 'Coca-Cola A', 'comercio_id' => 1, 'estado' => true]);
        Marca::create(['nombreMarca' => 'Pepsi B', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminA();
        $response = $this->get('/marcas');

        $response->assertOk();
        $content = $response->content();
        $this->assertStringContainsString('Coca-Cola A', $content);
        $this->assertStringNotContainsString('Pepsi B', $content);
    }

    public function test_marca_usuario_no_puede_editar_marca_de_otro_comercio(): void
    {
        $marcaB = Marca::create(['nombreMarca' => 'Marca B', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminA();
        $this->post('/marcas/' . $marcaB->id, [
            'nombreMarca' => 'Hackeado',
        ])->assertForbidden();
    }

    public function test_marca_usuario_no_puede_editar_marca_global(): void
    {
        $this->actingAsAdminA();
        $this->post('/marcas/1', [
            'nombreMarca' => 'Editado Global',
        ])->assertForbidden();
    }

    public function test_marca_usuario_no_puede_cambiar_status_marca_de_otro_comercio(): void
    {
        $marcaB = Marca::create(['nombreMarca' => 'Marca Status B', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminA();
        $this->patch('/marcas/' . $marcaB->id . '/status')->assertForbidden();
    }

    public function test_marca_usuario_puede_editar_su_propia_marca(): void
    {
        $marcaA = Marca::create(['nombreMarca' => 'Mi Marca', 'comercio_id' => 1, 'estado' => true]);

        $this->actingAsAdminA();
        $this->post('/marcas/' . $marcaA->id, [
            'nombreMarca' => 'Mi Marca Editada',
        ])->assertSessionMissing('error');
    }

    // =============================================
    // PROVEEDORES
    // =============================================

    public function test_proveedor_usa_solo_ve_registros_de_su_comercio_y_globales(): void
    {
        Proveedor::create([
            'razon_social' => 'Distribuidora A SRL', 'cuit' => '30-11111111-1',
            'comercio_id' => 1, 'estado' => true,
        ]);
        Proveedor::create([
            'razon_social' => 'Distribuidora B SRL', 'cuit' => '30-22222222-2',
            'comercio_id' => 2, 'estado' => true,
        ]);

        $this->actingAsAdminA();
        $response = $this->get('/proveedores');

        $response->assertOk();
        $response->assertSee('Proveedor General S.A.');
        $response->assertSee('Distribuidora A SRL');
        $response->assertDontSee('Distribuidora B SRL');
    }

    public function test_proveedor_usuario_no_puede_editar_proveedor_de_otro_comercio(): void
    {
        $provB = Proveedor::create([
            'razon_social' => 'Proveedor B SA', 'cuit' => '30-33333333-3',
            'comercio_id' => 2, 'estado' => true,
        ]);

        $this->actingAsAdminA();
        $this->put('/proveedores/' . $provB->id, [
            'razon_social' => 'Hackeado',
            'cuit' => '30-33333333-3',
        ])->assertForbidden();
    }

    public function test_proveedor_usuario_no_puede_editar_proveedor_global(): void
    {
        $this->actingAsAdminA();
        $this->put('/proveedores/1', [
            'razon_social' => 'Editado Global',
            'cuit' => '30-12345678-9',
        ])->assertForbidden();
    }

    public function test_proveedor_usuario_no_puede_cambiar_status_proveedor_de_otro_comercio(): void
    {
        $provB = Proveedor::create([
            'razon_social' => 'Proveedor Status B', 'cuit' => '30-44444444-4',
            'comercio_id' => 2, 'estado' => true,
        ]);

        $this->actingAsAdminA();
        $this->patch('/proveedores/' . $provB->id . '/status')->assertForbidden();
    }

    public function test_proveedor_usuario_puede_editar_su_propio_proveedor(): void
    {
        $provA = Proveedor::create([
            'razon_social' => 'Mi Proveedor', 'cuit' => '30-55555555-5',
            'comercio_id' => 1, 'estado' => true,
        ]);

        $this->actingAsAdminA();
        $this->put('/proveedores/' . $provA->id, [
            'razon_social' => 'Mi Proveedor Editado',
            'cuit' => '30555555555',
        ])->assertSessionHas('success');
    }

    // =============================================
    // ADMIN B: VERIFICA AISLAMIENTO INVERSO
    // =============================================

    public function test_categoria_admin_b_no_ve_categorias_de_comercio_a(): void
    {
        Categoria::create(['nombreCategoria' => 'Bebidas A', 'slug' => 'bebidas-a-admin', 'comercio_id' => 1, 'estado' => true]);
        Categoria::create(['nombreCategoria' => 'Bebidas B', 'slug' => 'bebidas-b-admin', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminB();
        $response = $this->get('/categorias');

        $response->assertOk();
        $response->assertSee('General');
        $response->assertSee('Bebidas B');
        $response->assertDontSee('Bebidas A');
    }

    public function test_marca_admin_b_no_ve_marcas_de_comercio_a(): void
    {
        Marca::create(['nombreMarca' => 'Marca Exclusiva A', 'comercio_id' => 1, 'estado' => true]);
        Marca::create(['nombreMarca' => 'Marca Exclusiva B', 'comercio_id' => 2, 'estado' => true]);

        $this->actingAsAdminB();
        $response = $this->get('/marcas');

        $response->assertOk();
        $response->assertSee('Marca Exclusiva B');
        $response->assertDontSee('Marca Exclusiva A');
    }

    public function test_proveedor_admin_b_no_ve_proveedores_de_comercio_a(): void
    {
        Proveedor::create([
            'razon_social' => 'Solo A SA', 'cuit' => '30-66666666-6',
            'comercio_id' => 1, 'estado' => true,
        ]);
        Proveedor::create([
            'razon_social' => 'Solo B SA', 'cuit' => '30-77777777-7',
            'comercio_id' => 2, 'estado' => true,
        ]);

        $this->actingAsAdminB();
        $response = $this->get('/proveedores');

        $response->assertOk();
        $response->assertSee('Solo B SA');
        $response->assertDontSee('Solo A SA');
    }
}
