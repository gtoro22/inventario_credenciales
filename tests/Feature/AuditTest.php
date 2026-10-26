<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Credential;
use App\Models\Project;
use LogicException;
use Tests\TestCase;

class AuditTest extends TestCase
{
    private const SECRET = 'audit-secret-value-77';
    private const NEW_SECRET = 'audit-NEW-secret-value-88';

    public function test_credential_lifecycle_is_audited_without_exposing_secrets(): void
    {
        $project = Project::factory()->create();
        $user = $this->userWithRole('project-manager', [$project]);
        $payload = [
            'project_id' => $project->id, 'credential_type_id' => 1, 'criticality_level_id' => 2, 'name' => 'Cred',
            'username' => 'u', 'secret' => self::SECRET, 'environment' => 'qa', 'status' => 'active', 'notes' => 'private-note-xyz',
        ];

        $id = $this->actingAs($user)->postJson('/api/credentials', $payload)->assertCreated()->json('data.id');
        $this->actingAs($user)->putJson("/api/credentials/{$id}", [...$payload, 'secret' => self::NEW_SECRET, 'name' => 'Cred 2', 'notes' => 'other-note'])->assertOk();
        $this->actingAs($user)->deleteJson("/api/credentials/{$id}")->assertNoContent();

        $this->assertSame(['credential.created', 'credential.updated', 'credential.deleted'], AuditLog::orderBy('id')->pluck('action')->all());

        $dump = AuditLog::all()->toJson();
        foreach ([self::SECRET, self::NEW_SECRET, 'private-note-xyz', 'other-note'] as $leak) {
            $this->assertStringNotContainsString($leak, $dump);
        }

        $update = AuditLog::where('action', 'credential.updated')->first();
        $this->assertSame('Cred', $update->old_values['name']);
        $this->assertSame('Cred 2', $update->new_values['name']);
        $this->assertArrayHasKey('secret', $update->new_values);
        $this->assertSame($project->id, $update->project_id);
        $this->assertSame($user->id, $update->user_id);
        $this->assertSame('Credential', $update->entity_type);
        $this->assertNotNull($update->ip_address);
        $this->assertNotNull($update->created_at);
    }

    public function test_user_role_and_project_assignment_changes_are_audited(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();

        $id = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nuevo', 'email' => 'nuevo@example.test', 'password' => 'Str0ng-Passw0rd!x',
            'role_ids' => [\App\Models\Role::where('slug', 'read-only')->value('id')], 'project_ids' => [$project->id],
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->putJson("/api/users/{$id}", [
            'name' => 'Nuevo', 'email' => 'nuevo@example.test', 'password' => 'An0ther-Passw0rd!y',
            'role_ids' => [\App\Models\Role::where('slug', 'auditor')->value('id')], 'project_ids' => [],
        ])->assertOk();

        $role = $this->actingAs($admin)->postJson('/api/roles', ['name' => 'Custom', 'permission_ids' => [1]])->assertCreated()->json('data.id');
        $this->actingAs($admin)->putJson("/api/roles/{$role}", ['name' => 'Custom', 'permission_ids' => [1, 2]])->assertOk();

        $actions = AuditLog::pluck('action')->all();
        $this->assertContains('user.created', $actions);
        $this->assertContains('user.updated', $actions);
        $this->assertContains('role.created', $actions);
        $this->assertContains('role.updated', $actions);

        $updated = AuditLog::where('action', 'user.updated')->first();
        $this->assertSame(['read-only'], $updated->old_values['roles']);
        $this->assertSame(['auditor'], $updated->new_values['roles']);
        $this->assertStringNotContainsString('An0ther', AuditLog::all()->toJson());
        $this->assertStringNotContainsString('Str0ng', AuditLog::all()->toJson());
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = app(\App\Services\AuditService::class)->log('test.event');

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = app(\App\Services\AuditService::class)->log('test.event');

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_only_auditors_can_read_logs_and_only_their_projects(): void
    {
        [$mine, $other] = Project::factory()->count(2)->create();
        $svc = app(\App\Services\AuditService::class);
        $svc->log('credential.created', projectId: $mine->id, label: 'visible');
        $svc->log('credential.created', projectId: $other->id, label: 'hidden');
        $svc->log('auth.login', label: 'global');

        $auditor = $this->userWithRole('auditor', [$mine]);

        $labels = collect($this->actingAs($auditor)->getJson('/api/audit-logs')->assertOk()->json('data'))->pluck('entity_label');
        $this->assertTrue($labels->contains('visible'));
        $this->assertTrue($labels->contains('global'));
        $this->assertFalse($labels->contains('hidden'));

        $this->assertCount(3, $this->actingAs($this->superAdmin())->getJson('/api/audit-logs')->json('data'));
    }

    public function test_audit_log_filters(): void
    {
        $svc = app(\App\Services\AuditService::class);
        $svc->log('credential.created', label: 'a');
        $svc->log('project.created', label: 'b');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/api/audit-logs?action=project.created')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/audit-logs?search=credential')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/audit-logs?from='.now()->addDay()->toDateString())->assertJsonCount(0, 'data');
    }

    public function test_password_change_is_audited(): void
    {
        $user = $this->userWithRole('read-only');

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'Test-Password-123!', 'password' => 'Brand-New-Passw0rd!', 'password_confirmation' => 'Brand-New-Passw0rd!',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed', 'user_id' => $user->id]);
        $this->assertStringNotContainsString('Brand-New', AuditLog::all()->toJson());
    }
}
