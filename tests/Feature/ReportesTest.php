<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    // Crea dos ventas reales usando el endpoint, para probar el flujo completo.
    private function crearVentas(): Producto
    {
        $producto = Producto::factory()->create([
            'precio_compra' => 1000,
            'precio_venta' => 1500,
            'stock' => 50,
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/ventas', ['metodo_pago' => 'efectivo', 'items' => [['producto_id' => $producto->id, 'cantidad' => 4]]])->assertCreated();
        $this->postJson('/api/ventas', ['metodo_pago' => 'nequi', 'items' => [['producto_id' => $producto->id, 'cantidad' => 6]]])->assertCreated();

        return $producto;
    }

    public function test_rentabilidad_calcula_ingresos_costo_ganancia_y_margen(): void
    {
        $this->crearVentas();
        Sanctum::actingAs(User::factory()->admin()->create());

        // 10 unidades: ingresos 15000, costo 10000, ganancia 5000, margen 33.3 %.
        $this->getJson('/api/reportes/rentabilidad')
            ->assertOk()
            ->assertJsonPath('totales.ingresos', 15000)
            ->assertJsonPath('totales.costo', 10000)
            ->assertJsonPath('totales.ganancia', 5000)
            ->assertJsonPath('totales.margen', 33.3)
            ->assertJsonPath('productos.0.unidades', 10)
            ->assertJsonPath('productos.0.contribucion', 100);
    }

    public function test_cambiar_precio_de_compra_no_altera_el_reporte(): void
    {
        $producto = $this->crearVentas();

        // El costo del producto sube después de las ventas.
        $producto->update(['precio_compra' => 1400]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/reportes/rentabilidad')
            ->assertOk()
            ->assertJsonPath('totales.costo', 10000)
            ->assertJsonPath('totales.ganancia', 5000);
    }

    public function test_resumen_del_dia(): void
    {
        $this->crearVentas();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/reportes/resumen-dia')
            ->assertOk()
            ->assertJsonPath('ventas_total', 15000)
            ->assertJsonPath('pedidos', 2)
            ->assertJsonPath('ganancia_neta', 5000)
            ->assertJsonCount(2, 'ultimas_ventas')
            ->assertJsonCount(7, 'ultimos_7_dias');
    }

    public function test_resumen_del_cajero_solo_cuenta_sus_ventas(): void
    {
        $this->crearVentas(); // las hace un cajero (el último autenticado)
        $otro = User::factory()->create();
        Sanctum::actingAs($otro);

        $this->getJson('/api/mis-ventas/resumen')
            ->assertOk()
            ->assertJsonPath('cantidad', 0)
            ->assertJsonPath('total', 0);
    }
}
