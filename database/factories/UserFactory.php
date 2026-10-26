<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Contraseña solo para pruebas; nunca se usa fuera de la factory.
            'password' => static::$password ??= Hash::make('Test-Password-123!'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Asigna roles por slug tras crear (los roles deben existir: seed previo). */
    public function withRole(string ...$slugs): static
    {
        return $this->afterCreating(function (User $user) use ($slugs) {
            $user->roles()->syncWithoutDetaching(Role::whereIn('slug', $slugs)->pluck('id'));
            $user->flushPermissionCache();
        });
    }

    public function superAdmin(): static
    {
        return $this->withRole(Role::SUPER_ADMIN);
    }
}
