<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'description' => null, 'is_system' => false];
    }
}
