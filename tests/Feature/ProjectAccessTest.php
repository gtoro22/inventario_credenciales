<?php

namespace Tests\Feature;

use App\Models\Credential;
use App\Models\Project;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    public function test_super_admin_sees_every_project(): void
    {
        Project::factory()->count(3)->create();

        $this->actingAs($this->superAdmin())->getJson('/api/projects')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_regular_user_only_sees_assigned_projects(): void
    {
        [$a, $b] = Project::factory()->count(2)->create();
        Credential::factory()->count(2)->create(['project_id' => $a->id]);
        Credential::factory()->create(['project_id' => $b->id]);
        $user = $this->userWithRole('project-manager', [$a]);

        $this->actingAs($user)->getJson('/api/projects')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id)->assertJsonPath('data.0.credentials_count', 2);

        $this->actingAs($user)->getJson('/api/credentials')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_foreign_project_looks_non_existent(): void
    {
        $foreign = Project::factory()->create();
        $credential = Credential::factory()->create(['project_id' => $foreign->id]);
        $user = $this->userWithRole('project-manager');

        $this->actingAs($user)->getJson("/api/projects/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->putJson("/api/projects/{$foreign->id}", ['name' => 'x', 'code' => 'X'])->assertNotFound();
        $this->actingAs($user)->getJson("/api/credentials/{$credential->id}")->assertNotFound();
        $this->actingAs($user)->get("/projects/{$foreign->id}")->assertNotFound();
    }

    public function test_cannot_create_credential_in_foreign_project(): void
    {
        $foreign = Project::factory()->create();
        $user = $this->userWithRole('project-manager');

        $this->actingAs($user)->postJson('/api/credentials', $this->payload($foreign))
            ->assertUnprocessable()->assertJsonValidationErrors('project_id');
    }

    public function test_creating_a_project_grants_access_to_its_creator(): void
    {
        $user = $this->userWithRole('project-manager');

        $id = $this->actingAs($user)->postJson('/api/projects', ['name' => 'Nuevo', 'code' => 'NEW-1', 'tags' => ['a']])
            ->assertCreated()->json('data.id');

        $this->assertTrue($user->fresh()->canAccessProject($id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.created', 'project_id' => $id, 'user_id' => $user->id]);
    }

    public function test_project_code_must_be_unique_and_well_formed(): void
    {
        Project::factory()->create(['code' => 'DUP']);

        $this->actingAs($this->superAdmin())->postJson('/api/projects', ['name' => 'x', 'code' => 'DUP'])->assertJsonValidationErrors('code');
        $this->actingAs($this->superAdmin())->postJson('/api/projects', ['name' => 'x', 'code' => 'bad code!'])->assertJsonValidationErrors('code');
    }

    public function test_delete_requires_inactive_project_and_cascades_credentials(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->count(2)->create(['project_id' => $project->id]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->deleteJson("/api/projects/{$project->id}")->assertUnprocessable();

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}/active", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->actingAs($admin)->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertSoftDeleted($project);
        $this->assertSame(0, Credential::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.deleted', 'project_id' => $project->id]);
    }

    public function test_listing_filters_and_search(): void
    {
        Project::factory()->create(['name' => 'Alpha', 'code' => 'AAA']);
        Project::factory()->inactive()->create(['name' => 'Beta', 'code' => 'BBB']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/api/projects?search=alp')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/projects?status=inactive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'BBB');
        $this->actingAs($admin)->getJson('/api/projects?per_page=1')->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 2);
    }

    public function test_project_detail_includes_metrics(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->critical()->create(['project_id' => $project->id]);
        Credential::factory()->level('low')->create(['project_id' => $project->id]);

        $this->actingAs($this->superAdmin())->getJson("/api/projects/{$project->id}")->assertOk()
            ->assertJsonPath('data.credentials_count', 2)->assertJsonPath('data.critical_count', 1)
            ->assertJsonStructure(['metrics' => ['by_criticality', 'by_type', 'by_status', 'expiring']]);
    }

    private function payload(Project $project): array
    {
        return [
            'project_id' => $project->id, 'credential_type_id' => 1, 'criticality_level_id' => 1,
            'name' => 'x', 'environment' => 'qa', 'status' => 'active',
        ];
    }
}
