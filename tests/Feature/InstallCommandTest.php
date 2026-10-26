<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_install_creates_super_admin_with_generated_password(): void
    {
        $this->artisan('credentials:install', ['--email' => 'root@example.test', '--no-interaction' => true])
            ->expectsOutputToContain('Instalación completada')
            ->assertSuccessful();

        $user = User::where('email', 'root@example.test')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->is_active);
        $this->assertSame(Permission::count(), count($user->permissionSlugs()));
    }

    public function test_install_is_idempotent_and_does_not_create_second_admin(): void
    {
        $this->artisan('credentials:install', ['--email' => 'root@example.test', '--password' => 'Chosen-Passw0rd!99', '--no-interaction' => true])->assertSuccessful();
        $hash = User::where('email', 'root@example.test')->value('password');
        $roles = Role::count();

        $this->artisan('credentials:install', ['--no-interaction' => true])->expectsOutputToContain('Ya existe un superadministrador')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame($roles, Role::count());
        $this->assertSame($hash, User::where('email', 'root@example.test')->value('password'));
    }

    public function test_demo_data_flag_creates_sample_projects_and_encrypted_credentials(): void
    {
        $this->artisan('credentials:install', ['--email' => 'root@example.test', '--with-demo-data' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertSame(3, Project::count());
        $this->assertSame(15, \App\Models\Credential::count());
        $raw = \Illuminate\Support\Facades\DB::table('credentials')->value('secret');
        $this->assertStringNotContainsString('demo-', $raw);
    }

    public function test_rotate_key_command_reencrypts(): void
    {
        $project = Project::factory()->create();
        $credential = \App\Models\Credential::factory()->create(['project_id' => $project->id, 'secret' => 'rotate-me']);
        $before = $credential->getRawOriginal('secret');

        $this->artisan('credentials:rotate-key', ['--force' => true])->assertSuccessful();

        $after = \Illuminate\Support\Facades\DB::table('credentials')->value('secret');
        $this->assertNotSame($before, $after);
        $this->assertSame('rotate-me', $credential->fresh()->secret);
    }
}
