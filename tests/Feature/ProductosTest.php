<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductosTest extends TestCase
{
    use RefreshDatabase;

    public function test_precio_venta_menor_al_de_compra_devuelve_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/productos', [
            'codigo_barras' => '7700000000002',
            'nombre' => 'Producto malo',
            'categoria_id' => Categoria::factory()->create()->id,
            'precio_compra' => 2000,
            'precio_venta' => 1500,
            'stock' => 1,
            'stock_minimo' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('precio_venta');
    }

    public function test_filtro_stock_bajo(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Producto::factory()->create(['nombre' => 'Bajo', 'stock' => 2, 'stock_minimo' => 5]);
        Producto::factory()->create(['nombre' => 'Suficiente', 'stock' => 50, 'stock_minimo' => 5]);

        $this->getJson('/api/productos?stock_bajo=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Bajo')
            ->assertJsonPath('data.0.stock_bajo', true);
    }

    public function test_busqueda_por_codigo_de_barras(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Producto::factory()->create(['codigo_barras' => '7701234567890']);
        Producto::factory()->create(['codigo_barras' => '7709999999999']);

        $this->getJson('/api/productos?buscar=7701234')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cambiar_estado_alterna_activo(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $producto = Producto::factory()->create(['activo' => true]);

        $this->patchJson("/api/productos/{$producto->id}/estado")
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->patchJson("/api/productos/{$producto->id}/estado")
            ->assertOk()
            ->assertJsonPath('data.activo', true);
    }
}
