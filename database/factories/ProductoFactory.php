<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo_barras' => fake()->unique()->numerify('770#########'),
            'nombre' => fake()->unique()->words(3, true),
            'categoria_id' => Categoria::factory(),
            'precio_compra' => 1000,
            'precio_venta' => 1500,
            'stock' => 20,
            'stock_minimo' => 5,
            'activo' => true,
        ];
    }
}
