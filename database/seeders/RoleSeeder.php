<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(['nombre' => 'admin'], ['descripcion' => 'Administrador del sistema']);
        Role::updateOrCreate(['nombre' => 'cajero'], ['descripcion' => 'Cajero del punto de venta']);
    }
}
