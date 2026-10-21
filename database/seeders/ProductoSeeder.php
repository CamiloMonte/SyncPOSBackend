<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        // [código de barras, nombre, categoría, precio compra, precio venta, stock, stock mínimo]
        $productos = [
            ['7702004003508', 'Café Sello Rojo 500 g', 'Bebidas', 9800, 13500, 42, 10],
            ['7702007012345', 'Arroz Diana 1 kg', 'Granos', 3600, 4900, 8, 15],
            ['7702011045678', 'Aceite Gourmet 900 ml', 'Aceites', 11200, 15900, 26, 8],
            ['7702354001122', 'Leche Alquería 1 L', 'Lácteos', 3100, 4200, 4, 12],
            ['7702189023456', 'Azúcar Manuelita 1 kg', 'Granos', 3900, 5200, 55, 10],
            ['7702025109876', 'Galletas Festival x12', 'Snacks', 5400, 7800, 31, 10],
            ['7702133000874', 'Jabón Rey x3', 'Aseo', 4700, 6900, 3, 6],
            ['7702001048720', 'Huevos AA x30', 'Frescos', 14500, 18900, 18, 5],
            ['7702535011234', 'Gaseosa Postobón 1,5 L', 'Bebidas', 2900, 4500, 64, 12],
            ['7702048123987', 'Atún Van Camps 170 g', 'Enlatados', 4100, 5900, 6, 10],
        ];

        foreach ($productos as [$codigo, $nombre, $categoria, $compra, $venta, $stock, $minimo]) {
            Producto::updateOrCreate(['codigo_barras' => $codigo], [
                'nombre' => $nombre,
                'categoria_id' => Categoria::where('nombre', $categoria)->value('id'),
                'precio_compra' => $compra,
                'precio_venta' => $venta,
                'stock' => $stock,
                'stock_minimo' => $minimo,
                'activo' => true,
            ]);
        }
    }
}
