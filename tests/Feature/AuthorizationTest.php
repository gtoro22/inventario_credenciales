<?php

namespace Tests\Feature;

use App\Models\Credential;
use App\Models\Project;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function protectedEndpoints(): array
    {
        return [
            'users list needs users.manage' => ['GET', '/api/users', 'read-only'],
            'users create' => ['POST', '/api/users', 'project-manager'],
            'roles list' => ['GET', '/api/roles', 'read-only'],
            'role create needs roles.manage' => ['POST', '/api/roles', 'admin'],
            'permissions list' => ['GET', '/api/permissions', 'read-only'],
            'audit logs need audit.view' => ['GET', '/api/audit-logs', 'project-manager'],
            'project create needs projects.create' => ['POST', '/api/projects', 'read-only'],
            'credential create needs credentials.create' => ['POST', '/api/credentials', 'read-only'],
            'export needs credentials.export' => ['GET', '/api/credentials/export', 'project-manager'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_api_denies_users_without_permission(string $method, string $uri, string $role): void
    {
        $this->actingAs($this->userWithRole($role))->json($method, $uri)->assertForbidden()->assertJsonStructure(['message']);
    }

    public function test_pages_are_protected_by_permission(): void
    {
        $reader = $this->userWithRole('read-only');

        $this->actingAs($reader)->get('/users')->assertForbidden();
        $this->actingAs($reader)->get('/roles')->assertForbidden();
        $this->actingAs($reader)->get('/audit')->assertForbidden();
        $this->actingAs($reader)->get('/projects')->assertOk();
        $this->actingAs($reader)->get('/credentials')->assertOk();
    }

    public function test_user_without_dashboard_permission_is_redirected_to_settings(): void
    {
        $this->actingAs($this->userWithRole('read-only'))->get('/')->assertOk();

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect('/settings');
        $this->actingAs($user)->getJson('/api/dashboard/metrics')->assertForbidden();
    }

    public function test_read_only_user_cannot_modify_or_delete(): void
    {
        $project = Project::factory()->create();
        $credential = Credential::factory()->create(['project_id' => $project->id]);
        $reader = $this->userWithRole('read-only', [$project]);

        $this->actingAs($reader)->putJson("/api/credentials/{$credential->id}", [])->assertForbidden();
        $this->actingAs($reader)->deleteJson("/api/credentials/{$credential->id}")->assertForbidden();
        $this->actingAs($reader)->putJson("/api/projects/{$project->id}", [])->assertForbidden();
        $this->actingAs($reader)->deleteJson("/api/projects/{$project->id}")->assertForbidden();
        $this->actingAs($reader)->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true])->assertForbidden();
    }

    public function test_abilities_in_resources_reflect_permissions(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->userWithRole('read-only', [$project]))->getJson('/api/credentials')
            ->assertOk()->assertJsonPath('data.0.abilities', ['update' => false, 'delete' => false, 'reveal' => false, 'copy' => false]);

        $this->actingAs($this->userWithRole('project-manager', [$project]))->getJson('/api/credentials')
            ->assertOk()->assertJsonPath('data.0.abilities', ['update' => true, 'delete' => true, 'reveal' => true, 'copy' => true]);
    }

    public function test_api_errors_use_consistent_json_shape(): void
    {
        $user = $this->userWithRole('project-manager');

        $this->actingAs($user)->getJson('/api/credentials/999999')->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
        $this->actingAs($user)->postJson('/api/projects', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors' => ['name', 'code']]);
    }
}
