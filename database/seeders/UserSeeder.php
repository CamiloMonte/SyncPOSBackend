<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ATENCIÓN: la contraseña "password" es solo para desarrollo. Cámbiala en producción.
        // El modelo User ya cifra la contraseña automáticamente (cast "hashed").
        User::updateOrCreate(['email' => 'admin@syncpos.co'], [
            'name' => 'Andrés Díaz',
            'password' => 'password',
            'role_id' => Role::where('nombre', 'admin')->value('id'),
            'activo' => true,
        ]);

        User::updateOrCreate(['email' => 'cajero@syncpos.co'], [
            'name' => 'Laura Gómez',
            'password' => 'password',
            'role_id' => Role::where('nombre', 'cajero')->value('id'),
            'activo' => true,
        ]);
    }
}
