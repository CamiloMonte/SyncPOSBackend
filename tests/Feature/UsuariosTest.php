<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function datosNuevos(array $extra = []): array
    {
        return array_merge([
            'name' => 'Cajero Nuevo',
            'email' => 'nuevo@syncpos.test',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'rol' => 'cajero',
        ], $extra);
    }

    private function crearVenta(User $user): void
    {
        Venta::create([
            'user_id' => $user->id,
            'total' => 1000,
            'metodo_pago' => 'efectivo',
            'origen' => 'online',
            'vendida_en' => now(),
        ]);
    }

    public function test_cajero_recibe_403_en_todos_los_endpoints(): void
    {
        $otro = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/usuarios')->assertStatus(403);
        $this->getJson("/api/usuarios/{$otro->id}")->assertStatus(403);
        $this->postJson('/api/usuarios', $this->datosNuevos())->assertStatus(403);
        $this->putJson("/api/usuarios/{$otro->id}", $this->datosNuevos())->assertStatus(403);
        $this->patchJson("/api/usuarios/{$otro->id}/estado")->assertStatus(403);
    }

    public function test_admin_lista_usuarios_con_ventas_count_y_filtros(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
        $cajero = User::factory()->create(['name' => 'Beto Cajero']);
        $this->crearVenta($cajero);
        $this->crearVenta($cajero);
        Sanctum::actingAs($admin);

        $respuesta = $this->getJson('/api/usuarios')->assertOk();
        $respuesta->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana Admin')
            ->assertJsonPath('data.0.rol', 'admin')
            ->assertJsonPath('data.1.rol', 'cajero')
            ->assertJsonPath('data.1.ventas_count', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'rol', 'activo', 'ventas_count', 'created_at']]]);

        $this->getJson('/api/usuarios?rol=cajero')->assertJsonCount(1, 'data');
        $this->getJson('/api/usuarios?buscar=beto')->assertJsonCount(1, 'data');

        $cajero->update(['activo' => false]);
        $this->getJson('/api/usuarios?activo=0')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beto Cajero');
        $this->getJson('/api/usuarios?activo=1')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana Admin');
    }

    public function test_admin_ve_un_usuario(): void
    {
        $cajero = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/usuarios/{$cajero->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $cajero->email);
    }

    public function test_crear_valida_los_datos(): void
    {
        $existente = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/usuarios', $this->datosNuevos(['email' => $existente->email]))
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/usuarios', $this->datosNuevos(['password' => 'corta', 'password_confirmation' => 'corta']))
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/usuarios', $this->datosNuevos(['password_confirmation' => 'otra-distinta']))
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/usuarios', $this->datosNuevos(['rol' => 'superman']))
            ->assertStatus(422)->assertJsonValidationErrors('rol');
    }

    public function test_admin_crea_un_cajero_que_puede_iniciar_sesion(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/usuarios', $this->datosNuevos())
            ->assertCreated()
            ->assertJsonPath('data.rol', 'cajero')
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.ventas_count', 0);

        // Se prueba el login real del nuevo usuario.
        $this->postJson('/api/login', ['email' => 'nuevo@syncpos.test', 'password' => 'secreto123'])
            ->assertOk()
            ->assertJsonPath('user.rol', 'cajero');
    }

    public function test_admin_actualiza_nombre_y_rol(): void
    {
        $cajero = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson("/api/usuarios/{$cajero->id}", [
            'name' => 'Nombre Editado',
            'email' => $cajero->email, // conservar el propio correo es valido
            'rol' => 'admin',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nombre Editado')
            ->assertJsonPath('data.rol', 'admin');
    }

    public function test_cambiar_la_contrasena_revoca_los_tokens(): void
    {
        $cajero = User::factory()->create();
        $cajero->createToken('syncpos');
        Sanctum::actingAs(User::factory()->admin()->create());

        // Sin contrasena: los tokens se conservan.
        $this->putJson("/api/usuarios/{$cajero->id}", [
            'name' => 'Sin Cambio', 'email' => $cajero->email, 'rol' => 'cajero',
        ])->assertOk();
        $this->assertSame(1, $cajero->tokens()->count());

        // Con contrasena nueva: se borran.
        $this->putJson("/api/usuarios/{$cajero->id}", [
            'name' => 'Con Cambio', 'email' => $cajero->email, 'rol' => 'cajero',
            'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1',
        ])->assertOk();
        $this->assertSame(0, $cajero->tokens()->count());

        $this->postJson('/api/login', ['email' => $cajero->email, 'password' => 'nueva-clave-1'])->assertOk();
    }

    public function test_desactivar_revoca_tokens_y_bloquea_el_login(): void
    {
        $cajero = User::factory()->create();
        $cajero->createToken('syncpos');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/usuarios/{$cajero->id}/estado")
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertSame(0, $cajero->tokens()->count());
        $this->postJson('/api/login', ['email' => $cajero->email, 'password' => 'password'])
            ->assertStatus(403);

        // Un segundo toggle lo reactiva.
        $this->patchJson("/api/usuarios/{$cajero->id}/estado")->assertJsonPath('data.activo', true);
    }

    public function test_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/usuarios/{$admin->id}/estado")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No puedes desactivar tu propia cuenta.');

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_admin_no_puede_quitarse_el_rol_de_administrador(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/usuarios/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'rol' => 'cajero',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('rol')
            ->assertJsonPath('errors.rol.0', 'No puedes quitarte el rol de administrador.');

        $this->assertTrue($admin->fresh()->esAdmin());
    }
}
