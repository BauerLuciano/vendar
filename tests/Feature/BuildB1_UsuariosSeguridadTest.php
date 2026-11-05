<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCaseMultiTenant;

class BuildB1_UsuariosSeguridadTest extends TestCaseMultiTenant
{
    private function crearAdminGlobal(): User
    {
        $admin = new User();
        $admin->name = 'Admin Global Test';
        $admin->email = 'global.' . uniqid() . '@vendar.test';
        $admin->password = Hash::make('password123');
        $admin->comercio_id = null;
        $admin->branch_id = null;
        $admin->is_active = true;
        $admin->save();
        $admin->assignRole('Administrador Global');

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan',
            'apellido' => 'Pérez',
            'email' => 'nuevo.' . uniqid() . '.' . random_int(1000, 9999) . '@vendar.test',
            'telefono' => '+54 9 375 512-3456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'branch_id' => 1,
            'rol' => 'Cajero',
            'sucursales' => [1, 2],
        ], $overrides);
    }

    private function idsDeUsuarios($response): array
    {
        return collect($response->viewData('page')['props']['usuarios']['data'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ============================================================
    // AISLAMIENTO MULTI-TENANT
    // ============================================================

    public function test_comercio_a_solo_lista_sus_usuarios(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/usuarios');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Usuarios/Index')
            ->where('esAdminGlobal', false)
            ->has('usuarios.data')
        );

        $ids = $this->idsDeUsuarios($response);

        $this->assertContains((int) $this->userA->id, $ids);
        $this->assertNotContains((int) $this->userB->id, $ids);
        $this->assertNotContains((int) $this->adminB->id, $ids);
    }

    public function test_comercio_b_solo_lista_sus_usuarios(): void
    {
        $this->actingAsAdminB();

        $response = $this->get('/usuarios');
        $response->assertOk();

        $ids = $this->idsDeUsuarios($response);

        $this->assertContains((int) $this->userB->id, $ids);
        $this->assertNotContains((int) $this->userA->id, $ids);
        $this->assertNotContains((int) $this->adminA->id, $ids);
    }

    public function test_comercio_a_no_puede_consultar_usuario_de_b(): void
    {
        $this->actingAsAdminA();

        $this->get('/usuarios/' . $this->userB->id)->assertForbidden();
    }

    public function test_comercio_a_no_puede_modificar_usuario_de_b(): void
    {
        $this->actingAsAdminA();

        $this->put('/usuarios/' . $this->userB->id, [
            'name' => 'Hackeado',
            'email' => $this->userB->email,
            'branch_id' => 3,
            'rol' => 'Cajero',
        ])->assertForbidden();
    }

    public function test_comercio_a_no_puede_eliminar_usuario_de_b(): void
    {
        $this->actingAsAdminA();

        $this->delete('/usuarios/' . $this->userB->id)->assertForbidden();
    }

    public function test_comercio_a_no_puede_asignar_sucursal_base_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload([
            'branch_id' => 3,
            'sucursales' => [3],
        ]))->assertSessionHasErrors(['branch_id']);
    }

    public function test_comercio_a_no_puede_asignar_sucursal_adicional_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload([
            'branch_id' => 1,
            'sucursales' => [1, 3],
        ]))->assertSessionHasErrors(['sucursales.1']);
    }

    public function test_superadmin_no_puede_asignar_administrador_global(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload([
            'rol' => 'Administrador Global',
        ]))->assertSessionHasErrors(['rol']);
    }

    public function test_peticion_directa_de_comercio_no_puede_cambiar_el_comercio_del_usuario(): void
    {
        $this->actingAsAdminA();

        $email = 'directo.' . uniqid() . '@vendar.test';

        $this->post('/usuarios', $this->payload([
            'email' => $email,
            'comercio_id' => 2,
            'branch_id' => 1,
            'sucursales' => [1],
        ]))->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'comercio_id' => 1,
        ]);
    }

    // ============================================================
    // ADMINISTRADOR GLOBAL
    // ============================================================

    public function test_admin_global_puede_listar_usuarios_de_distintos_comercios(): void
    {
        $global = $this->crearAdminGlobal();
        $this->actingAs($global);

        $response = $this->get('/usuarios');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('esAdminGlobal', true)
            ->has('comercios', 2)
        );

        $ids = $this->idsDeUsuarios($response);

        $this->assertContains((int) $this->adminA->id, $ids);
        $this->assertContains((int) $this->adminB->id, $ids);
    }

    public function test_admin_global_puede_crear_usuario_seleccionando_comercio(): void
    {
        $global = $this->crearAdminGlobal();
        $this->actingAs($global);

        $email = 'globalcreado.' . uniqid() . '@vendar.test';

        $this->post('/usuarios', $this->payload([
            'email' => $email,
            'comercio_id' => 2,
            'branch_id' => 3,
            'sucursales' => [3, 4],
        ]))->assertRedirect();

        $usuario = User::where('email', $email)->firstOrFail();

        $this->assertSame(2, (int) $usuario->comercio_id);
        $this->assertSame(3, (int) $usuario->branch_id);
        $this->assertEqualsCanonicalizing(
            [3, 4],
            $usuario->sucursales()->pluck('sucursales.id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertDatabaseHas('activity_log', [
            'description' => 'usuario_administrado_por_administrador_global',
            'subject_id' => $usuario->id,
        ]);
    }

    public function test_admin_global_no_puede_crear_usuario_sin_comercio(): void
    {
        $global = $this->crearAdminGlobal();
        $this->actingAs($global);

        $this->post('/usuarios', $this->payload())
            ->assertSessionHasErrors(['comercio_id']);
    }

    public function test_admin_global_no_puede_asignar_sucursal_de_otro_comercio(): void
    {
        $global = $this->crearAdminGlobal();
        $this->actingAs($global);

        $this->post('/usuarios', $this->payload([
            'comercio_id' => 2,
            'branch_id' => 1,
            'sucursales' => [1],
        ]))->assertSessionHasErrors(['branch_id']);
    }

    public function test_admin_global_mantiene_facultades_de_administracion(): void
    {
        $global = $this->crearAdminGlobal();
        $this->actingAs($global);

        $this->get('/usuarios/' . $this->userA->id)->assertOk();

        $this->put('/usuarios/' . $this->userA->id, [
            'name' => 'Cajero Global Editado',
            'email' => $this->userA->email,
            'branch_id' => 1,
            'rol' => 'Cajero',
            'comercio_id' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->userA->id,
            'name' => 'Cajero Global Editado',
        ]);
    }

    // ============================================================
    // VALIDACIONES DEL FORMULARIO
    // ============================================================

    public function test_nombre_con_numeros_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload(['name' => 'Juan123']))
            ->assertSessionHasErrors(['name']);
    }

    public function test_apellido_con_simbolos_no_permitidos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload(['apellido' => 'Pérez@']))
            ->assertSessionHasErrors(['apellido']);
    }

    public function test_email_invalido_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload(['email' => 'no-es-email']))
            ->assertSessionHasErrors(['email']);
    }

    public function test_email_duplicado_es_rechazado_sin_importar_mayusculas(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload([
            'email' => strtoupper($this->userA->email),
        ]))->assertSessionHasErrors(['email']);
    }

    public function test_telefono_valido_es_aceptado(): void
    {
        $this->actingAsAdminA();

        $email = 'telefono.' . uniqid() . '@vendar.test';

        $this->post('/usuarios', $this->payload([
            'email' => $email,
            'telefono' => '+54 9 375 512-3456',
        ]))->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'telefono' => '+54 9 375 512-3456',
        ]);
    }

    public function test_telefono_con_letras_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload(['telefono' => '375ABC123']))
            ->assertSessionHasErrors(['telefono']);
    }

    public function test_password_y_confirmacion_diferentes_son_rechazadas(): void
    {
        $this->actingAsAdminA();

        $this->post('/usuarios', $this->payload([
            'password_confirmation' => 'otra12345',
        ]))->assertSessionHasErrors(['password']);
    }

    public function test_usuario_existente_sin_apellido_sigue_funcionando(): void
    {
        $this->assertNull($this->userA->apellido);

        $this->actingAsAdminA();

        $this->get('/usuarios')->assertOk();

        $this->put('/usuarios/' . $this->userA->id, [
            'name' => 'Cajero Norte',
            'email' => $this->userA->email,
            'branch_id' => 1,
            'rol' => 'Cajero',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->userA->id,
            'name' => 'Cajero Norte',
            'apellido' => null,
        ]);
    }

    public function test_edicion_sin_password_conserva_password_anterior(): void
    {
        $hashAnterior = $this->userA->password;

        $this->actingAsAdminA();

        $this->put('/usuarios/' . $this->userA->id, [
            'name' => 'User A Sin Password',
            'email' => $this->userA->email,
            'branch_id' => 1,
            'rol' => 'Cajero',
        ])->assertRedirect();

        $this->assertSame($hashAnterior, $this->userA->fresh()->password);
    }

    // ============================================================
    // ROLES DE SISTEMA
    // ============================================================

    public function test_superadmin_no_puede_editar_roles_de_sistema(): void
    {
        $this->actingAsAdminA();

        foreach (['SuperAdmin', 'Administrador Global'] as $nombre) {
            $role = Role::where('name', $nombre)->firstOrFail();

            $this->put('/roles/' . $role->id, [
                'nombre' => $nombre,
                'permisos' => [],
            ])->assertForbidden();
        }
    }

    public function test_superadmin_no_puede_renombrar_rol_protegido(): void
    {
        $this->actingAsAdminA();

        $role = Role::where('name', 'SuperAdmin')->firstOrFail();

        $this->put('/roles/' . $role->id, [
            'nombre' => 'SuperAdmin Renombrado',
            'permisos' => [],
        ])->assertForbidden();
    }

    public function test_superadmin_no_puede_eliminar_roles_de_sistema(): void
    {
        $this->actingAsAdminA();

        foreach (['SuperAdmin', 'Administrador Global'] as $nombre) {
            $role = Role::where('name', $nombre)->firstOrFail();

            $this->delete('/roles/' . $role->id)
                ->assertSessionHasErrors('error');

            $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => $nombre]);
        }
    }

    public function test_superadmin_no_puede_crear_rol_con_nombre_de_sistema(): void
    {
        $this->actingAsAdminA();

        $this->post('/roles', [
            'nombre' => 'Administrador Global',
            'permisos' => [],
        ])->assertSessionHasErrors(['nombre']);
    }

    public function test_permisos_existentes_siguen_funcionando(): void
    {
        $this->actingAsAdminA();

        $this->assertTrue($this->adminA->hasRole('SuperAdmin'));
        $this->assertTrue($this->adminA->hasPermissionTo('gestionar usuarios'));

        $role = Role::create([
            'name' => 'Vendedor Test ' . uniqid(),
            'guard_name' => 'web',
        ]);

        $this->put('/roles/' . $role->id, [
            'nombre' => $role->name,
            'permisos' => ['vender en pos'],
        ])->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('vender en pos'));
    }
}
