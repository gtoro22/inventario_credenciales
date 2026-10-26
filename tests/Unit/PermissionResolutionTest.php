<?php

namespace Tests\Unit;

use App\Models\Credential;
use App\Models\CriticalityLevel;
use App\Models\Project;
use App\Policies\CredentialPolicy;
use Tests\TestCase;

class PermissionResolutionTest extends TestCase
{
    public function test_permissions_are_union_of_roles_and_profiles(): void
    {
        $user = $this->userWithRole('read-only', profiles: ['critical-access']);

        $this->assertTrue($user->hasPermission('projects.view'));      // rol
        $this->assertTrue($user->hasPermission('credentials.critical')); // perfil
        $this->assertFalse($user->hasPermission('credentials.reveal'));
    }

    public function test_super_admin_has_every_permission(): void
    {
        $admin = $this->superAdmin();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasPermission('roles.manage'));
        $this->assertTrue($admin->canAccessProject(Project::factory()->create()));
    }

    public function test_project_access_is_explicit_for_regular_users(): void
    {
        $mine = Project::factory()->create();
        $other = Project::factory()->create();
        $user = $this->userWithRole('project-manager', [$mine]);

        $this->assertTrue($user->canAccessProject($mine));
        $this->assertFalse($user->canAccessProject($other));
        $this->assertSame([$mine->id], Project::visibleTo($user)->pluck('id')->all());
    }

    public function test_critical_credentials_need_extra_permission_in_policy(): void
    {
        $project = Project::factory()->create();
        $manager = $this->userWithRole('project-manager', [$project]);
        $privileged = $this->userWithRole('project-manager', [$project], ['critical-access']);
        $critical = Credential::factory()->critical()->create(['project_id' => $project->id])->load('criticality');
        $policy = new CredentialPolicy;
        $level = CriticalityLevel::where('slug', 'critical')->first();

        $this->assertFalse($policy->view($manager, $critical));
        $this->assertFalse($policy->create($manager, $project, $level));
        $this->assertTrue($policy->view($privileged, $critical));
        $this->assertTrue($policy->reveal($privileged, $critical));
        $this->assertFalse($policy->reveal($manager, $critical));
    }
}
