<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // El orden importa: los roles deben existir antes que los usuarios, etc.
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CategoriaSeeder::class,
            ProductoSeeder::class,
        ]);
    }
}
