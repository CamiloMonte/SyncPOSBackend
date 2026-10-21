<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = ['Bebidas', 'Granos', 'Aceites', 'Lácteos', 'Snacks', 'Aseo', 'Frescos', 'Enlatados'];

        foreach ($categorias as $nombre) {
            Categoria::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
