<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCaseMultiTenant;

class Modulo6_UsuariosTest extends TestCaseMultiTenant
{
    private function uniqueEmail(): string
    {
        return 'test.' . uniqid() . '@vendar.test';
    }

    // U1.1.1
    public function test_admin_a_puede_crear_usuario(): void
    {
        $this->actingAsAdminA();

        $email = $this->uniqueEmail();
        $this->post('/usuarios', [
            'name' => 'Test User A',
            'email' => $email,
            'password' => 'password123',
            'branch_id' => 1,
            'rol' => 'Cajero',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'comercio_id' => 1,
        ]);
    }

    // U1.1.2
    public function test_admin_b_puede_crear_usuario(): void
    {
        $this->actingAsAdminB();

        $email = $this->uniqueEmail();
        $this->post('/usuarios', [
            'name' => 'Test User B',
            'email' => $email,
            'password' => 'password123',
            'branch_id' => 3,
            'rol' => 'Cajero',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'comercio_id' => 2,
        ]);
    }

    // U1.2.1
    public function test_admin_a_puede_editar_usuario_de_su_comercio(): void
    {
        $this->actingAsAdminA();

        $this->put('/usuarios/' . $this->userA->id, [
            'name' => 'User A Editado',
            'email' => $this->userA->email,
            'branch_id' => 1,
            'rol' => 'Cajero',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->userA->id,
            'name' => 'User A Editado',
        ]);
    }

    // U1.2.2
    public function test_admin_a_no_puede_editar_usuario_de_otro_comercio(): void
    {
        $this->actingAsAdminA();

        $this->put('/usuarios/' . $this->userB->id, [
            'name' => 'Hackeado',
            'email' => $this->userB->email,
            'branch_id' => 1,
            'rol' => 'Cajero',
        ])->assertForbidden();
    }

    // U1.3.1
    public function test_admin_a_puede_eliminar_usuario_de_su_comercio(): void
    {
        $this->actingAsAdminA();

        $this->delete('/usuarios/' . $this->userA->id)->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $this->userA->id]);
    }

    // U1.3.2
    public function test_admin_a_no_puede_eliminar_usuario_de_otro_comercio(): void
    {
        $this->actingAsAdminA();

        $this->delete('/usuarios/' . $this->userB->id)->assertForbidden();
    }
}
