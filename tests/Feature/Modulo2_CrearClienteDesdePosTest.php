<?php

namespace Tests\Feature;

use Tests\TestCaseMultiTenant;

class Modulo2_CrearClienteDesdePosTest extends TestCaseMultiTenant
{
    public function test_admin_a_puede_crear_cliente_desde_pos(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/pos/crear-cliente', [
            'nombre' => 'Nuevo',
            'apellido' => 'ClientePos',
        ]);

        $response->assertOk();
        $response->assertJsonPath('nombre', 'Nuevo');

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Nuevo',
            'apellido' => 'ClientePos',
            'comercio_id' => 1,
        ]);
    }

    public function test_no_puede_crear_cliente_desde_pos_sin_modulo_fiados(): void
    {
        $comercio = $this->adminA->branch->comercio;
        $comercio->update([
            'modulos_habilitados' => array_merge($comercio->modulos_habilitados ?? [], ['fiados' => false]),
        ]);

        $this->actingAsAdminA();

        $response = $this->post('/pos/crear-cliente', [
            'nombre' => 'Bloqueado',
            'apellido' => 'SinModulo',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('consumidores', ['nombre' => 'Bloqueado']);
    }

    // ----- Validación endurecida del modal del POS -----

    public function test_nombre_con_numeros_desde_pos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Nuevo1',
            'apellido' => 'ClientePos',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
        $this->assertDatabaseMissing('consumidores', ['nombre' => 'Nuevo1']);
    }

    public function test_nombre_corto_desde_pos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'N',
            'apellido' => 'ClientePos',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_apellido_con_numeros_desde_pos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Nuevo',
            'apellido' => 'Cliente5',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('apellido');
    }

    public function test_telefono_con_letras_desde_pos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Nuevo',
            'apellido' => 'ClientePos',
            'telefono' => '3758abc',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('telefono');
    }

    public function test_telefono_corto_desde_pos_es_rechazado(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Nuevo',
            'apellido' => 'ClientePos',
            'telefono' => '12345',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('telefono');
    }

    public function test_telefono_valido_desde_pos_es_aceptado(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Nuevo',
            'apellido' => 'ClientePos',
            'telefono' => '3758445566',
        ]);

        $response->assertOk();
        $this->assertSame('3758445566', $response->json('telefono'));
    }
}
