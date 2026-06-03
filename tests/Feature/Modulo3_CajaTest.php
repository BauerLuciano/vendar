<?php

namespace Tests\Feature;

use App\Models\TurnoCaja;
use Tests\TestCaseMultiTenant;

class Modulo3_CajaTest extends TestCaseMultiTenant
{
    // P3.1.1
    public function test_admin_a_puede_abrir_turno(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/api/sesiones-caja/abrir', [
            'caja' => 2,
            'saldo_inicial_efectivo' => 5000,
        ]);
        $response->assertJson(['message' => 'Caja abierta correctamente']);
    }

    // P3.1.2
    public function test_admin_b_puede_abrir_turno(): void
    {
        $this->actingAsAdminB();

        $response = $this->post('/api/sesiones-caja/abrir', [
            'caja' => 5,
            'saldo_inicial_efectivo' => 3000,
        ]);
        $response->assertJson(['message' => 'Caja abierta correctamente']);
    }

    // P3.1.3
    public function test_admin_a_no_puede_abrir_turno_en_caja_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/sesiones-caja/abrir', [
            'caja' => 4,
            'saldo_inicial_efectivo' => 5000,
        ])->assertNotFound();
    }

    // P3.2.1
    public function test_admin_a_puede_cerrar_turno(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/api/sesiones-caja/2/cerrar', [
            'saldo_final_efectivo_real' => 15000,
            'saldo_final_mp_real' => 0,
            'saldo_final_transf_real' => 0,
            'observaciones' => 'Cierre test',
        ]);
        $response->assertJson(['message' => 'Caja cerrada exitosamente']);
    }

    // P3.2.2
    public function test_admin_a_no_puede_cerrar_turno_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/sesiones-caja/4/cerrar', [
            'saldo_final_efectivo_real' => 10000,
            'saldo_final_mp_real' => 0,
            'saldo_final_transf_real' => 0,
            'observaciones' => 'Hack',
        ])->assertNotFound();
    }

    // P3.3.1
    public function test_admin_a_puede_ver_movimientos_de_su_turno(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/api/sesiones-caja/2/movimientos');
        $response->assertOk();
        $response->assertJsonIsArray();
    }

    // P3.3.2
    public function test_admin_a_no_puede_ver_movimientos_de_turno_de_b(): void
    {
        $this->actingAsAdminA();

        $this->get('/api/sesiones-caja/4/movimientos')->assertForbidden();
    }
}
