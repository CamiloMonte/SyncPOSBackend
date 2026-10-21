<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_correcto_devuelve_token_y_rol(): void
    {
        User::factory()->admin()->create(['email' => 'admin@test.co']);

        $this->postJson('/api/login', ['email' => 'admin@test.co', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'rol']])
            ->assertJsonPath('user.rol', 'admin');
    }

    public function test_contrasena_incorrecta_devuelve_422(): void
    {
        User::factory()->create(['email' => 'c@test.co']);

        $this->postJson('/api/login', ['email' => 'c@test.co', 'password' => 'incorrecta'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Credenciales incorrectas.');
    }

    public function test_usuario_inactivo_devuelve_403(): void
    {
        User::factory()->inactivo()->create(['email' => 'i@test.co']);

        $this->postJson('/api/login', ['email' => 'i@test.co', 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Usuario inactivo.');
    }

    public function test_me_requiere_token(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_con_token_devuelve_el_usuario(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('rol', 'cajero');
    }
}
