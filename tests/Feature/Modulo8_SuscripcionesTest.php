<?php

namespace Tests\Feature;

use Tests\TestCaseMultiTenant;

class Modulo8_SuscripcionesTest extends TestCaseMultiTenant
{
    // P7.1.1
    public function test_admin_a_puede_ver_mi_plan(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/mi-plan');
        $response->assertOk();
    }

    // P7.1.2
    public function test_user_a_no_puede_ver_mi_plan_por_rol(): void
    {
        $this->actingAsUserA();

        $this->get('/mi-plan')->assertForbidden();
    }

    // P7.1.3
    public function test_admin_a_puede_ver_plan_actual_api(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/api/mi-plan/plan-actual');
        $response->assertOk();
        $response->assertJsonStructure(['plan_id']);
    }
}
