<?php

namespace Tests;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    /** Crea un usuario con un rol (slug) y acceso a los proyectos indicados. */
    protected function userWithRole(string $role, array $projects = [], array $profiles = []): User
    {
        $user = User::factory()->withRole($role)->create();

        if ($projects) {
            $user->projects()->sync(collect($projects)->map(fn (Project $p) => $p->id)->all());
        }
        if ($profiles) {
            $user->profiles()->sync(\App\Models\Profile::whereIn('slug', $profiles)->pluck('id'));
        }

        return $user->fresh();
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create()->fresh();
    }
}
