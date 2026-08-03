<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RoleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PlanSeeder::class]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->has('planes', 3)
            ->where('planes.0.trial_dias', 7)
            ->where('planes.0.trial_activo', true)
        );
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'plan_deseado' => 'Plan Básico',
            'nombre_comercio' => 'Mi Kiosco',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('comercios', ['nombre' => 'Mi Kiosco', 'status' => 'trial']);
        $this->assertDatabaseHas('sucursales', ['nombre' => 'Mi Kiosco']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'is_active' => true]);
    }

    public function test_new_saas_user_gets_superadmin_role_and_links(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'plan_deseado' => 'Plan Básico',
            'nombre_comercio' => 'Mi Kiosco',
        ]);

        $user = \App\Models\User::where('email', 'test@example.com')->first();

        $this->assertTrue($user->hasRole('SuperAdmin'));
        $this->assertNotNull($user->comercio_id);
        $this->assertNotNull($user->branch_id);
        $this->assertSame(1, $user->comercio->sucursales()->count());
    }

    public function test_plan_profesional_asigna_el_plan_pro_y_el_trial_del_plan(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'plan_deseado' => 'Plan Profesional',
            'nombre_comercio' => 'Mi Kiosco Pro',
        ]);

        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $comercio = $user->comercio;

        $this->assertSame('pro', $comercio->plan);
        $this->assertSame(14, $comercio->plan_id ? \App\Models\Plan::find($comercio->plan_id)->trial_dias : null);
        $this->assertSame('trial', $comercio->status);
        $this->assertNotNull($comercio->vencimiento_pago);
        $this->assertSame(now()->addDays(14)->toDateString(), $comercio->vencimiento_pago->toDateString());
    }
}
