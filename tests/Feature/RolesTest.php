<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function datosProducto(Categoria $categoria): array
    {
        return [
            'codigo_barras' => '7700000000001',
            'nombre' => 'Producto de prueba',
            'categoria_id' => $categoria->id,
            'precio_compra' => 1000,
            'precio_venta' => 1500,
            'stock' => 10,
            'stock_minimo' => 2,
        ];
    }

    public function test_cajero_no_puede_crear_productos(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/productos', $this->datosProducto(Categoria::factory()->create()))
            ->assertStatus(403)
            ->assertJsonPath('message', 'No tienes permiso para esta acción.');
    }

    public function test_admin_puede_crear_productos(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/productos', $this->datosProducto(Categoria::factory()->create()))
            ->assertCreated()
            ->assertJsonPath('data.codigo_barras', '7700000000001');
    }

    public function test_cajero_no_puede_ver_reportes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reportes/rentabilidad')->assertStatus(403);
    }
}
