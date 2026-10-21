<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Por defecto el usuario es cajero; el rol se crea si no existe.
            'role_id' => fn () => Role::firstOrCreate(['nombre' => 'cajero'])->id,
            'activo' => true,
        ];
    }

    // Estado para crear un administrador: User::factory()->admin()->create()
    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['nombre' => 'admin'])->id,
        ]);
    }

    // Estado para crear un usuario inactivo.
    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
