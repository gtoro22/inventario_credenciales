<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Persona', 'email' => 'persona@example.test', 'password' => 'Str0ng-Passw0rd!x', 'is_active' => true,
            'role_ids' => [Role::where('slug', 'read-only')->value('id')],
        ], $overrides);
    }

    public function test_super_admin_crud_users_with_roles_profiles_and_projects(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();
        $profile = Profile::where('slug', 'critical-access')->first();

        $id = $this->actingAs($admin)->postJson('/api/users', $this->userPayload(['profile_ids' => [$profile->id], 'project_ids' => [$project->id]]))
            ->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');

        $user = User::find($id);
        $this->assertTrue($user->hasPermission('credentials.critical'));
        $this->assertTrue($user->canAccessProject($project));
        $this->assertTrue(password_verify('Str0ng-Passw0rd!x', $user->password));

        $this->actingAs($admin)->putJson("/api/users/{$id}", $this->userPayload(['name' => 'Editada', 'password' => null]))->assertOk()->assertJsonPath('data.name', 'Editada');
        $this->assertTrue(password_verify('Str0ng-Passw0rd!x', $user->fresh()->password));

        $this->actingAs($admin)->deleteJson("/api/users/{$id}")->assertNoContent();
        $this->assertModelMissing($user);
    }

    public function test_weak_passwords_and_duplicate_emails_are_rejected(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($admin)->postJson('/api/users', $this->userPayload(['password' => 'short']))->assertJsonValidationErrors('password');
        $this->actingAs($admin)->postJson('/api/users', $this->userPayload(['email' => 'taken@example.test']))->assertJsonValidationErrors('email');
    }

    public function test_super_admin_cannot_delete_or_deactivate_self(): void
    {
        $admin = $this->superAdmin();
        $this->superAdmin(); // hay otro, el bloqueo es por "uno mismo"

        $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();
        $this->actingAs($admin)->putJson("/api/users/{$admin->id}", $this->userPayload(['email' => $admin->email, 'is_active' => false, 'role_ids' => [Role::where('slug', 'super-admin')->value('id')]]))
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertModelExists($admin);
    }

    public function test_last_active_super_admin_is_protected(): void
    {
        $last = $this->superAdmin();
        $inactiveSuper = $this->superAdmin();
        $inactiveSuper->update(['is_active' => false]);
        $service = app(UserService::class);
        $readOnly = Role::where('slug', 'read-only')->value('id');

        $attempts = [
            'delete' => fn () => $service->delete($inactiveSuper, $last),
            'deactivate' => fn () => $service->update($inactiveSuper, $last, ['is_active' => false]),
            'demote' => fn () => $service->update($inactiveSuper, $last, ['role_ids' => [$readOnly]]),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("Se permitió '{$name}' sobre el último superadministrador.");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('último superadministrador', collect($e->errors())->flatten()->implode(' '));
            }
        }

        $this->assertTrue($last->fresh()->isSuperAdmin());
        $this->assertTrue($last->fresh()->is_active);
    }

    public function test_super_admin_can_be_demoted_when_another_active_one_exists(): void
    {
        $actor = $this->superAdmin();
        $target = $this->superAdmin();

        $this->actingAs($actor)->putJson("/api/users/{$target->id}", $this->userPayload([
            'email' => $target->email, 'password' => null, 'role_ids' => [Role::where('slug', 'read-only')->value('id')],
        ]))->assertOk();

        $this->assertFalse($target->fresh()->isSuperAdmin());
        $this->assertTrue($actor->fresh()->isSuperAdmin());
    }

    public function test_super_admin_cannot_remove_own_super_admin_role(): void
    {
        $actor = $this->superAdmin();
        $this->superAdmin();

        $this->actingAs($actor)->putJson("/api/users/{$actor->id}", $this->userPayload([
            'email' => $actor->email, 'password' => null, 'role_ids' => [Role::where('slug', 'read-only')->value('id')],
        ]))->assertUnprocessable()->assertJsonValidationErrors('role_ids');
    }

    public function test_admin_cannot_assign_super_admin_role_or_escalate_privileges(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/users', $this->userPayload(['role_ids' => [Role::where('slug', 'super-admin')->value('id')]]))
            ->assertUnprocessable()->assertJsonValidationErrors('role_ids');

        // Un gestor con users.manage (vía perfil) no puede conceder permisos que no posee.
        $limited = $this->userWithRole('read-only');
        $profile = Profile::factory()->create();
        $profile->permissions()->sync(\App\Models\Permission::whereIn('slug', ['users.manage'])->pluck('id'));
        $limited->profiles()->attach($profile);
        $limited = $limited->fresh();

        $this->actingAs($limited)->postJson('/api/users', $this->userPayload(['role_ids' => [Role::where('slug', 'admin')->value('id')]]))
            ->assertUnprocessable();
        $this->actingAs($limited)->postJson('/api/users', $this->userPayload(['email' => 'ok@example.test']))->assertCreated();
    }

    public function test_non_super_admin_cannot_manage_super_admin_users(): void
    {
        $admin = $this->userWithRole('admin');
        $super = $this->superAdmin();

        $this->actingAs($admin)->getJson("/api/users/{$super->id}")->assertForbidden();
        $this->actingAs($admin)->putJson("/api/users/{$super->id}", $this->userPayload(['email' => $super->email]))->assertForbidden();
    }

    public function test_project_assignment_by_limited_admin_does_not_detach_unseen_projects(): void
    {
        [$a, $b] = Project::factory()->count(2)->create();
        $admin = $this->userWithRole('admin', [$a]);
        $target = $this->userWithRole('read-only', [$a, $b]);

        $this->actingAs($admin)->putJson("/api/users/{$target->id}", $this->userPayload(['email' => $target->email, 'password' => null, 'project_ids' => []]))->assertOk();

        $this->assertEqualsCanonicalizing([$b->id], $target->fresh()->projects()->pluck('projects.id')->all());
    }

    public function test_users_listing_search_and_filters(): void
    {
        User::factory()->create(['name' => 'Zulema Test']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/api/users?search=zulema')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/users?role=super-admin')->assertJsonCount(1, 'data');
    }

    public function test_role_management_rules(): void
    {
        $admin = $this->superAdmin();
        $superRole = Role::where('slug', 'super-admin')->first();

        $this->actingAs($admin)->putJson("/api/roles/{$superRole->id}", ['name' => 'X', 'permission_ids' => [1]])->assertUnprocessable();
        $this->actingAs($admin)->deleteJson("/api/roles/{$superRole->id}")->assertUnprocessable();

        $id = $this->actingAs($admin)->postJson('/api/roles', ['name' => 'Soporte', 'permission_ids' => [1, 2]])->assertCreated()->assertJsonPath('data.slug', 'soporte')->json('data.id');
        $this->actingAs($admin)->deleteJson("/api/roles/{$id}")->assertNoContent();

        $this->actingAs($admin)->getJson('/api/permissions')->assertOk()->assertJsonCount(17, 'data');
        $this->actingAs($admin)->getJson('/api/roles')->assertOk()->assertJsonStructure(['data' => [['id', 'slug', 'name', 'permission_ids', 'users_count']]]);
    }

    public function test_profiles_crud_and_assigned_profile_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $id = $this->actingAs($admin)->postJson('/api/profiles', ['name' => 'Soporte L2', 'permission_ids' => [1]])->assertCreated()->json('data.id');
        $user = User::factory()->create();
        $user->profiles()->attach($id);

        $this->actingAs($admin)->deleteJson("/api/profiles/{$id}")->assertUnprocessable();
        $user->profiles()->detach();
        $this->actingAs($admin)->deleteJson("/api/profiles/{$id}")->assertNoContent();
    }
}
