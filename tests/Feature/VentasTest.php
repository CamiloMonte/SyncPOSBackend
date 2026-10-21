<?php

namespace Tests\Feature;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_venta_descuenta_stock_y_calcula_total_en_el_servidor(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create([
            'precio_compra' => 1000,
            'precio_venta' => 1500,
            'stock' => 10,
        ]);

        // El cliente intenta enviar un precio falso: debe ignorarse.
        $this->postJson('/api/ventas', [
            'metodo_pago' => 'efectivo',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 3, 'precio_unitario' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.total', '4500.00')
            ->assertJsonPath('data.origen', 'online');

        $this->assertSame(7, $producto->fresh()->stock);

        $detalle = DetalleVenta::first();
        $this->assertSame('1500.00', $detalle->precio_unitario);
        $this->assertSame('1000.00', $detalle->costo_unitario);
        $this->assertSame('4500.00', $detalle->subtotal);
    }

    public function test_lineas_duplicadas_se_unen(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['stock' => 10]);

        $this->postJson('/api/ventas', [
            'metodo_pago' => 'nequi',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2],
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ])->assertCreated();

        $this->assertSame(1, DetalleVenta::count());
        $this->assertSame(5, DetalleVenta::first()->cantidad);
        $this->assertSame(5, $producto->fresh()->stock);
    }

    public function test_stock_insuficiente_devuelve_422_y_no_guarda_nada(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $suficiente = Producto::factory()->create(['stock' => 10]);
        $escaso = Producto::factory()->create(['nombre' => 'Leche escasa', 'stock' => 1]);

        $this->postJson('/api/ventas', [
            'metodo_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $suficiente->id, 'cantidad' => 2],
                ['producto_id' => $escaso->id, 'cantidad' => 5],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Leche escasa'));

        $this->assertSame(0, Venta::count());
        $this->assertSame(10, $suficiente->fresh()->stock);
        $this->assertSame(1, $escaso->fresh()->stock);
    }

    public function test_producto_inactivo_no_se_puede_vender(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['activo' => false]);

        $this->postJson('/api/ventas', [
            'metodo_pago' => 'efectivo',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(422);
    }

    public function test_cajero_solo_ve_sus_ventas_en_el_historial(): void
    {
        $laura = User::factory()->create();
        $pedro = User::factory()->create();
        Venta::create(['user_id' => $laura->id, 'total' => 100, 'metodo_pago' => 'efectivo', 'vendida_en' => now()]);
        Venta::create(['user_id' => $pedro->id, 'total' => 200, 'metodo_pago' => 'efectivo', 'vendida_en' => now()]);

        Sanctum::actingAs($laura);

        $this->getJson('/api/ventas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cajero.id', $laura->id);
    }

    public function test_admin_ve_todas_las_ventas(): void
    {
        $cajero = User::factory()->create();
        Venta::create(['user_id' => $cajero->id, 'total' => 100, 'metodo_pago' => 'efectivo', 'vendida_en' => now()]);
        Venta::create(['user_id' => $cajero->id, 'total' => 200, 'metodo_pago' => 'nequi', 'vendida_en' => now()]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/ventas')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/ventas?metodo_pago=nequi')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cajero_recibe_403_al_ver_venta_de_otro(): void
    {
        $otro = User::factory()->create();
        $venta = Venta::create(['user_id' => $otro->id, 'total' => 100, 'metodo_pago' => 'efectivo', 'vendida_en' => now()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/ventas/{$venta->id}")->assertStatus(403);
    }

    public function test_costo_solo_visible_para_admin(): void
    {
        $producto = Producto::factory()->create(['stock' => 10]);

        Sanctum::actingAs(User::factory()->create());
        $respuesta = $this->postJson('/api/ventas', [
            'metodo_pago' => 'efectivo',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertCreated();
        $this->assertArrayNotHasKey('costo_unitario', $respuesta->json('data.items.0'));

        $ventaId = $respuesta->json('data.id');
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson("/api/ventas/{$ventaId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.costo_unitario', '1000.00');
    }
}
