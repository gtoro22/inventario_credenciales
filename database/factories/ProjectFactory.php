<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => Str::upper(fake()->unique()->bothify('PRJ-####')),
            'description' => fake()->sentence(),
            'is_active' => true,
            'tags' => [fake()->word()],
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
