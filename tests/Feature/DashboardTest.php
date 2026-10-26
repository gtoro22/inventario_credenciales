<?php

namespace Tests\Feature;

use App\Models\Credential;
use App\Models\Project;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_metrics_respect_project_assignments(): void
    {
        [$mine, $other] = Project::factory()->count(2)->create();
        Credential::factory()->count(2)->level('low')->create(['project_id' => $mine->id]);
        Credential::factory()->count(5)->level('high')->create(['project_id' => $other->id]);
        $user = $this->userWithRole('project-manager', [$mine]);

        $data = $this->actingAs($user)->getJson('/api/dashboard/metrics')->assertOk()->json('data');

        $this->assertSame(1, $data['totals']['projects']);
        $this->assertSame(2, $data['totals']['credentials']);
        $this->assertCount(1, $data['projects']);
        $this->assertSame($mine->name, $data['by_project'][0]['name']);
        $this->assertSame(2, collect($data['by_criticality'])->sum('count'));
        $this->assertStringNotContainsString($other->name, json_encode($data));
    }

    public function test_critical_counts_are_hidden_without_critical_permission(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->count(2)->critical()->create(['project_id' => $project->id]);
        Credential::factory()->level('low')->create(['project_id' => $project->id]);

        $manager = $this->userWithRole('project-manager', [$project]);
        $this->actingAs($manager)->getJson('/api/dashboard/metrics')->assertJsonPath('data.totals.critical', 0)->assertJsonPath('data.totals.credentials', 1);

        $privileged = $this->userWithRole('project-manager', [$project], ['critical-access']);
        $this->actingAs($privileged)->getJson('/api/dashboard/metrics')->assertJsonPath('data.totals.critical', 2)->assertJsonPath('data.totals.credentials', 3);
    }

    public function test_super_admin_sees_everything_and_expiring_list(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->expiringIn(5)->create(['project_id' => $project->id, 'name' => 'soon']);
        Credential::factory()->expiringIn(200)->create(['project_id' => $project->id, 'name' => 'later']);

        $data = $this->actingAs($this->superAdmin())->getJson('/api/dashboard/metrics')->assertOk()->json('data');

        $this->assertSame(2, $data['totals']['credentials']);
        $this->assertSame(['soon'], collect($data['expiring'])->pluck('name')->all());
        $this->assertSame(5, $data['expiring'][0]['days_left']);
        $this->assertNotEmpty($data['by_type']);
    }

    public function test_without_credentials_view_no_credential_numbers_are_exposed(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->count(3)->create(['project_id' => $project->id]);
        $user = \App\Models\User::factory()->create();
        $user->projects()->attach($project);
        $profile = \App\Models\Profile::factory()->create();
        $profile->permissions()->sync(\App\Models\Permission::whereIn('slug', ['dashboard.view', 'projects.view'])->pluck('id'));
        $user->profiles()->attach($profile);

        $data = $this->actingAs($user->fresh())->getJson('/api/dashboard/metrics')->assertOk()->json('data');

        $this->assertFalse($data['can_view_credentials']);
        $this->assertSame(0, $data['totals']['credentials']);
        $this->assertSame([], $data['expiring']);
    }
}
