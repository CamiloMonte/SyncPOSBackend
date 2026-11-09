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

    // DEF-04: la app Android envia la hora con su desfase (ISO 8601, por ejemplo -05:00 en Colombia).
    // El servidor debe convertirla a su zona horaria antes de guardarla; si no, la venta queda corrida.
    public function test_hora_con_desfase_se_convierte_a_la_zona_del_servidor(): void
    {
        config(['app.timezone' => 'UTC']);
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['stock' => 5]);

        $venta = $this->ventaOffline((string) Str::uuid(), $producto->id, 1);
        $venta['vendida_en'] = '2026-11-04T10:15:00-05:00'; // formato que envia Android

        $this->postJson('/api/sync/ventas', ['ventas' => [$venta]])->assertOk();

        // 10:15 en Colombia (UTC-5) son las 15:15 en UTC
        $this->assertSame('2026-11-04 15:15:00', Venta::first()->vendida_en->format('Y-m-d H:i:s'));
    }

    // DEF-04: en MySQL/MariaDB una columna TIMESTAMP NOT NULL recibia "ON UPDATE CURRENT_TIMESTAMP"
    // y la hora de la venta se reemplazaba cada vez que se actualizaba la fila (por ejemplo el total).
    public function test_actualizar_la_venta_no_cambia_la_hora_en_que_se_vendio(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $producto = Producto::factory()->create(['stock' => 5]);

        $this->postJson('/api/sync/ventas', ['ventas' => [$this->ventaOffline((string) Str::uuid(), $producto->id, 1)]])
            ->assertOk();

        $venta = Venta::first();
        $venta->update(['total' => 999]);

        $this->assertSame('2026-10-01 10:30:00', $venta->fresh()->vendida_en->format('Y-m-d H:i:s'));
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
