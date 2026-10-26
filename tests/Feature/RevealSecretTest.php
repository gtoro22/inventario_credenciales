<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Credential;
use App\Models\Project;
use Tests\TestCase;

class RevealSecretTest extends TestCase
{
    private const SECRET = 'reveal-me-5f3a9c';

    private function setUpCredential(): array
    {
        $project = Project::factory()->create();
        $credential = Credential::factory()->level('high')->create(['project_id' => $project->id, 'secret' => self::SECRET]);

        return [$project, $credential];
    }

    public function test_authorized_user_can_reveal_with_explicit_confirmation_and_it_is_audited(): void
    {
        [$project, $credential] = $this->setUpCredential();
        $user = $this->userWithRole('project-manager', [$project]);

        $response = $this->actingAs($user)->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true])
            ->assertOk()->assertJsonPath('data.secret', self::SECRET);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $log = AuditLog::where('action', 'credential.secret_revealed')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($credential->id, $log->entity_id);
        $this->assertSame($project->id, $log->project_id);
        $this->assertNotNull($log->ip_address);
        $this->assertStringNotContainsString(self::SECRET, json_encode($log->toArray()));
    }

    public function test_reveal_requires_explicit_confirmation(): void
    {
        [$project, $credential] = $this->setUpCredential();
        $user = $this->userWithRole('project-manager', [$project]);

        $this->actingAs($user)->postJson("/api/credentials/{$credential->id}/reveal")->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($user)->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => false])->assertUnprocessable();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'credential.secret_revealed']);
    }

    public function test_users_without_reveal_permission_are_denied_and_nothing_is_logged_as_revealed(): void
    {
        [$project, $credential] = $this->setUpCredential();

        foreach (['read-only', 'auditor'] as $role) {
            $this->actingAs($this->userWithRole($role, [$project]))
                ->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true])->assertForbidden();
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'credential.secret_revealed']);
    }

    public function test_user_without_project_access_gets_404(): void
    {
        [, $credential] = $this->setUpCredential();

        $this->actingAs($this->userWithRole('project-manager'))
            ->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true])->assertNotFound();
    }

    public function test_copy_purpose_needs_copy_permission_and_logs_separately(): void
    {
        [$project, $credential] = $this->setUpCredential();
        $viewOnly = $this->userWithRole('read-only', [$project], ['credential-operator']);
        $viewOnly->profiles()->detach(); // sin perfil: solo lectura

        // Perfil con reveal pero sin copy.
        $revealer = \App\Models\Profile::factory()->create();
        $revealer->permissions()->sync(\App\Models\Permission::where('slug', 'credentials.reveal')->pluck('id'));
        $viewOnly->profiles()->attach($revealer);
        $viewOnly->flushPermissionCache();
        $viewOnly = $viewOnly->fresh();

        $this->actingAs($viewOnly)->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true, 'purpose' => 'copy'])->assertForbidden();
        $this->actingAs($viewOnly)->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true])->assertOk();

        $manager = $this->userWithRole('project-manager', [$project]);
        $this->actingAs($manager)->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true, 'purpose' => 'copy'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'credential.secret_copied', 'user_id' => $manager->id]);
    }

    public function test_super_admin_can_reveal_critical_secret(): void
    {
        $project = Project::factory()->create();
        $credential = Credential::factory()->critical()->create(['project_id' => $project->id, 'secret' => self::SECRET]);

        $this->actingAs($this->superAdmin())->postJson("/api/credentials/{$credential->id}/reveal", ['confirm' => true])
            ->assertOk()->assertJsonPath('data.secret', self::SECRET);
    }
}
