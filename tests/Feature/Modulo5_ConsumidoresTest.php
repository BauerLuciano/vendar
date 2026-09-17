<?php

namespace Tests\Feature;

use App\Models\Consumidor;
use Tests\TestCaseMultiTenant;

class Modulo5_ConsumidoresTest extends TestCaseMultiTenant
{
    // P4.1.1
    public function test_admin_a_puede_crear_consumidor(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'comercio_id' => 1,
        ]);
    }

    // P4.1.2
    public function test_admin_b_puede_crear_consumidor(): void
    {
        $this->actingAsAdminB();

        $response = $this->post('/clientes', [
            'nombre' => 'Lucía',
            'apellido' => 'Mendoza',
            'documento' => '87654321',
            'limite_cuenta_corriente' => 3000,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Lucía',
            'apellido' => 'Mendoza',
            'documento' => '87654321',
            'comercio_id' => 2,
        ]);
    }

    // P4.1.3 — contraseñas que no coinciden deben rechazarse
    public function test_crear_consumidor_rechaza_contrasenas_distintas(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '11112222',
            'limite_cuenta_corriente' => 5000,
            'password' => '123456',
            'password_confirmation' => '654321',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertStringContainsString('Las contraseñas no coinciden.', session('errors')->get('password')[0]);

        $this->assertDatabaseMissing('consumidores', ['documento' => '11112222']);
    }

    // P4.1.4 — contraseñas que coinciden deben guardarse
    public function test_crear_consumidor_acepta_contrasenas_iguales(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '11112222',
            'limite_cuenta_corriente' => 5000,
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '11112222',
            'comercio_id' => 1,
        ]);
    }

    // P4.2.1
    public function test_a_solo_ve_sus_consumidores(): void
    {
        $this->actingAsAdminA();

        $ids = Consumidor::where('comercio_id', 1)->pluck('id');

        $this->assertContains($this->consumidorA->id, $ids);
        $this->assertNotContains($this->consumidorB->id, $ids);
    }

    // P4.2.2
    public function test_admin_a_puede_ver_cuenta_de_su_consumidor(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/consumidores/'.$this->consumidorA->id.'/cuenta');
        $response->assertOk();
        $response->assertJsonIsArray();
    }

    // P4.2.3
    public function test_admin_a_no_puede_ver_cuenta_de_consumidor_de_b(): void
    {
        $this->actingAsAdminA();

        $this->get('/consumidores/'.$this->consumidorB->id.'/cuenta')->assertForbidden();
    }

    // P4.3.1
    public function test_admin_a_puede_cobrar_deuda(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/consumidores/'.$this->consumidorA->id.'/cobrar', [
            'pagos' => [
                ['monto' => 1000, 'metodo_pago' => 'Efectivo'],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    // P4.3.2
    public function test_admin_a_no_puede_cobrar_deuda_de_consumidor_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/consumidores/'.$this->consumidorB->id.'/cobrar', [
            'pagos' => [
                ['monto' => 500, 'metodo_pago' => 'Efectivo'],
            ],
        ])->assertForbidden();
    }

    // ----- Validación endurecida del alta de cliente -----

    public function test_nombre_vacio_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => '',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasErrors('nombre');
    }

    public function test_nombre_demasiado_corto_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'A',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ]);

        $response->assertSessionHasErrors('nombre');
        $this->assertStringContainsString('El nombre debe tener al menos 2 caracteres.', session('errors')->get('nombre')[0]);
    }

    public function test_nombre_demasiado_largo_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => str_repeat('Juan', 15),
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasErrors('nombre');
    }

    public function test_nombre_con_numeros_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto1',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ]);

        $response->assertSessionHasErrors('nombre');
        $this->assertStringContainsString('El nombre solo puede incluir letras y espacios.', session('errors')->get('nombre')[0]);
    }

    public function test_nombre_con_caracteres_no_permitidos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto@#$',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasErrors('nombre');
    }

    public function test_nombre_con_tildes_y_n_es_aceptado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'María Ñusta',
            'apellido' => 'García Juárez',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'María Ñusta',
            'apellido' => 'García Juárez',
            'comercio_id' => 1,
        ]);
    }

    public function test_nombre_de_varias_palabras_es_aceptado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Juan Carlos',
            'apellido' => 'De la Cruz',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Juan Carlos',
            'apellido' => 'De la Cruz',
            'comercio_id' => 1,
        ]);
    }

    public function test_nombre_con_espacios_al_inicio_y_fin_se_normaliza(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => '  Roberto  ',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Roberto',
            'comercio_id' => 1,
        ]);
    }

    public function test_apellido_con_numeros_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González9',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasErrors('apellido');
    }

    public function test_telefono_demasiado_corto_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'telefono' => '1234567',
            'limite_cuenta_corriente' => 5000,
        ]);

        $response->assertSessionHasErrors('telefono');
        $this->assertStringContainsString('El teléfono debe tener al menos 8 dígitos.', session('errors')->get('telefono')[0]);
    }

    public function test_telefono_demasiado_largo_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'telefono' => '1234567890123456',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasErrors('telefono');
    }

    public function test_telefono_con_letras_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'telefono' => '3758abc456',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasErrors('telefono');
    }

    public function test_telefono_valido_es_aceptado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'telefono' => '3758445566',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumidores', [
            'telefono' => '3758445566',
            'comercio_id' => 1,
        ]);
    }

    public function test_email_invalido_sin_dominio_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'email' => 'nombre@',
            'limite_cuenta_corriente' => 5000,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Ingresá un email válido.', session('errors')->get('email')[0]);
    }

    public function test_email_valido_es_aceptado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'email' => 'roberto@ejemplo.com.ar',
            'limite_cuenta_corriente' => 5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumidores', [
            'email' => 'roberto@ejemplo.com.ar',
            'comercio_id' => 1,
        ]);
    }

    public function test_limite_cuenta_corriente_mayor_al_maximo_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 100000000,
        ])->assertSessionHasErrors('limite_cuenta_corriente');
    }
}
