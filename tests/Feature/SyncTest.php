<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private function ventaOffline(string $referencia, int $productoId, int $cantidad): array
    {
        return [
            'offline_referencia' => $referencia,
            'metodo_pago' => 'efectivo',
            'vendida_en' => '2026-10-01 10:30:00',
            'items' => [['producto_id' => $productoId, 'cantidad' => $cantidad]],
        ];
    }

    public function test_la_misma_referencia_dos_veces_es_duplicada_y_descuenta_stock_una_vez(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['stock' => 10]);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/sync/ventas', ['ventas' => [$this->ventaOffline($uuid, $producto->id, 2)]])
            ->assertOk()
            ->assertJsonPath('resultados.0.estado', 'sincronizada');

        $segunda = $this->postJson('/api/sync/ventas', ['ventas' => [$this->ventaOffline($uuid, $producto->id, 2)]])
            ->assertOk()
            ->assertJsonPath('resultados.0.estado', 'duplicada');

        $this->assertSame(1, Venta::count());
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertSame(Venta::first()->id, $segunda->json('resultados.0.venta_id'));
    }

    public function test_la_app_puede_marcar_una_venta_enviada_al_instante_como_online(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['stock' => 10]);

        $online = $this->ventaOffline((string) Str::uuid(), $producto->id, 1) + ['origen' => 'online'];
        $sinOrigen = $this->ventaOffline((string) Str::uuid(), $producto->id, 1);

        $this->postJson('/api/sync/ventas', ['ventas' => [$online, $sinOrigen]])->assertOk();

        $this->assertSame(['online', 'offline'], Venta::orderBy('id')->pluck('origen')->all());
        // Solo la venta que espero sin conexion tiene fecha de sincronizacion.
        $this->assertNull(Venta::where('origen', 'online')->first()->sincronizada_en);
        $this->assertNotNull(Venta::where('origen', 'offline')->first()->sincronizada_en);
    }

    public function test_un_origen_invalido_se_rechaza(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create();

        $venta = $this->ventaOffline((string) Str::uuid(), $producto->id, 1) + ['origen' => 'por_telefono'];

        $this->postJson('/api/sync/ventas', ['ventas' => [$venta]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ventas.0.origen');
    }

    public function test_venta_offline_conserva_fecha_origen_y_puede_dejar_stock_negativo(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['nombre' => 'Arroz', 'stock' => 1]);

        $this->postJson('/api/sync/ventas', ['ventas' => [$this->ventaOffline((string) Str::uuid(), $producto->id, 3)]])
            ->assertOk()
            ->assertJsonPath('resultados.0.estado', 'sincronizada')
            ->assertJsonPath('resultados.0.stock_negativo', true);

        $this->assertSame(-2, $producto->fresh()->stock);

        $venta = Venta::first();
        $this->assertSame('offline', $venta->origen);
        $this->assertSame('2026-10-01 10:30:00', $venta->vendida_en->format('Y-m-d H:i:s'));
        $this->assertNotNull($venta->sincronizada_en);
    }

    public function test_producto_inactivo_se_rechaza_y_las_demas_ventas_se_sincronizan(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $inactivo = Producto::factory()->create(['activo' => false, 'stock' => 10]);
        $activo = Producto::factory()->create(['stock' => 10]);
        $malaRef = (string) Str::uuid();
        $buenaRef = (string) Str::uuid();

        $respuesta = $this->postJson('/api/sync/ventas', ['ventas' => [
            $this->ventaOffline($malaRef, $inactivo->id, 1),
            $this->ventaOffline($buenaRef, $activo->id, 1),
        ]])->assertOk();

        $respuesta->assertJsonPath('resultados.0.estado', 'rechazada');
        $respuesta->assertJsonPath('resultados.1.estado', 'sincronizada');
        $this->assertNotEmpty($respuesta->json('resultados.0.mensaje'));

        $this->assertSame(1, Venta::count());
        $this->assertSame(10, $inactivo->fresh()->stock);
        $this->assertSame(9, $activo->fresh()->stock);
    }

    public function test_maximo_100_ventas_por_lote(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create();
        $ventas = array_map(fn () => $this->ventaOffline((string) Str::uuid(), $producto->id, 1), range(1, 101));

        $this->postJson('/api/sync/ventas', ['ventas' => $ventas])->assertStatus(422);
    }
}
