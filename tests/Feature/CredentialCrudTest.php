<?php

namespace Tests\Feature;

use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredentialCrudTest extends TestCase
{
    private const SECRET = 'P4ss-w0rd-THAT-must-never-leak';

    private function payload(Project $project, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $project->id,
            'credential_type_id' => CredentialType::where('slug', 'database')->value('id'),
            'criticality_level_id' => CriticalityLevel::where('slug', 'medium')->value('id'),
            'name' => 'DB principal',
            'username' => 'app',
            'secret' => self::SECRET,
            'host' => 'db.example.test',
            'port' => 3306,
            'environment' => 'production',
            'status' => 'active',
            'notes' => 'nota interna',
            'expires_at' => now()->addDays(10)->toDateString(),
        ], $overrides);
    }

    public function test_create_stores_secret_encrypted_and_never_returns_it(): void
    {
        $project = Project::factory()->create();
        $user = $this->userWithRole('project-manager', [$project]);

        $response = $this->actingAs($user)->postJson('/api/credentials', $this->payload($project))->assertCreated();

        $response->assertJsonPath('data.secret', '********')->assertJsonPath('data.has_secret', true)
            ->assertJsonPath('data.created_by', $user->name)->assertJsonPath('data.status.value', 'active');
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());

        $raw = DB::table('credentials')->first();
        $this->assertNotSame(self::SECRET, $raw->secret);
        $this->assertStringNotContainsString(self::SECRET, $raw->secret);
        $this->assertSame(self::SECRET, Credential::first()->secret);
        $this->assertSame($user->id, (int) $raw->created_by);
    }

    public function test_secret_is_absent_from_listing_detail_and_serialization(): void
    {
        $project = Project::factory()->create();
        $credential = Credential::factory()->create(['project_id' => $project->id, 'secret' => self::SECRET]);
        $user = $this->userWithRole('project-manager', [$project]);

        $this->assertStringNotContainsString(self::SECRET, $this->actingAs($user)->getJson('/api/credentials')->getContent());
        $this->assertStringNotContainsString(self::SECRET, $this->actingAs($user)->getJson("/api/credentials/{$credential->id}")->getContent());
        $this->assertStringNotContainsString(self::SECRET, $credential->toJson());
    }

    public function test_update_keeps_secret_when_omitted_and_replaces_when_given(): void
    {
        $project = Project::factory()->create();
        $credential = Credential::factory()->create(['project_id' => $project->id, 'secret' => self::SECRET]);
        $user = $this->userWithRole('project-manager', [$project]);
        $payload = $this->payload($project, ['name' => 'Renombrada']);
        unset($payload['secret']);

        $this->actingAs($user)->putJson("/api/credentials/{$credential->id}", $payload)->assertOk()->assertJsonPath('data.name', 'Renombrada')
            ->assertJsonPath('data.updated_by', $user->name);
        $this->assertSame(self::SECRET, $credential->fresh()->secret);

        $this->actingAs($user)->putJson("/api/credentials/{$credential->id}", $this->payload($project, ['secret' => 'new-secret-value']))->assertOk();
        $this->assertSame('new-secret-value', $credential->fresh()->secret);
    }

    public function test_delete_soft_deletes(): void
    {
        $project = Project::factory()->create();
        $credential = Credential::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->userWithRole('project-manager', [$project]))->deleteJson("/api/credentials/{$credential->id}")->assertNoContent();

        $this->assertSoftDeleted($credential);
    }

    public function test_validation_rules(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->superAdmin())->postJson('/api/credentials', $this->payload($project, [
            'environment' => 'moon', 'status' => 'weird', 'port' => 70000, 'name' => '', 'credential_type_id' => 9999,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['environment', 'status', 'port', 'name', 'credential_type_id']);
    }

    public function test_filters_and_pagination(): void
    {
        $p1 = Project::factory()->create();
        $p2 = Project::factory()->create();
        Credential::factory()->create(['project_id' => $p1->id, 'name' => 'alpha-db', 'environment' => 'production', 'status' => 'active']);
        Credential::factory()->critical()->create(['project_id' => $p2->id, 'name' => 'beta-ssh', 'environment' => 'qa', 'status' => 'revoked']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/api/credentials?search=alpha')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson("/api/credentials?project_id={$p2->id}")->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/credentials?environment=production')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/credentials?status=revoked')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/credentials?criticality=critical')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/credentials?per_page=1')->assertJsonPath('meta.total', 2)->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_critical_credentials_are_hidden_and_protected_without_extra_permission(): void
    {
        $project = Project::factory()->create();
        $critical = Credential::factory()->critical()->create(['project_id' => $project->id]);
        Credential::factory()->level('low')->create(['project_id' => $project->id]);
        $manager = $this->userWithRole('project-manager', [$project]);

        $this->actingAs($manager)->getJson('/api/credentials')->assertJsonCount(1, 'data');
        $this->actingAs($manager)->getJson("/api/credentials/{$critical->id}")->assertNotFound();
        $this->actingAs($manager)->putJson("/api/credentials/{$critical->id}", $this->payload($project))->assertNotFound();
        $this->actingAs($manager)->deleteJson("/api/credentials/{$critical->id}")->assertNotFound();
        $this->actingAs($manager)->postJson("/api/credentials/{$critical->id}/reveal", ['confirm' => true])->assertNotFound();

        // No puede crear una crítica ni escalar una existente a crítica.
        $criticalLevel = CriticalityLevel::where('slug', 'critical')->value('id');
        $this->actingAs($manager)->postJson('/api/credentials', $this->payload($project, ['criticality_level_id' => $criticalLevel]))->assertForbidden();
        $mine = Credential::factory()->level('low')->create(['project_id' => $project->id]);
        $this->actingAs($manager)->putJson("/api/credentials/{$mine->id}", $this->payload($project, ['criticality_level_id' => $criticalLevel]))->assertForbidden();

        // Con el perfil "Acceso a credenciales críticas" sí.
        $privileged = $this->userWithRole('project-manager', [$project], ['critical-access']);
        $this->actingAs($privileged)->getJson("/api/credentials/{$critical->id}")->assertOk();
        $this->actingAs($privileged)->postJson('/api/credentials', $this->payload($project, ['criticality_level_id' => $criticalLevel]))->assertCreated();
    }

    public function test_csv_export_never_contains_secrets_and_is_audited(): void
    {
        $project = Project::factory()->create();
        Credential::factory()->create(['project_id' => $project->id, 'name' => '=HYPERLINK("x")', 'secret' => self::SECRET]);
        $auditor = $this->userWithRole('auditor', [$project]);

        $response = $this->actingAs($auditor)->get('/api/credentials/export')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringNotContainsString(self::SECRET, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credentials.exported', 'user_id' => $auditor->id]);
    }

    public function test_expiration_command_marks_expired(): void
    {
        $project = Project::factory()->create();
        $old = Credential::factory()->create(['project_id' => $project->id, 'expires_at' => now()->subDay()->toDateString()]);
        $future = Credential::factory()->expiringIn(5)->create(['project_id' => $project->id]);

        $this->artisan('credentials:expire')->assertSuccessful();

        $this->assertSame('expired', $old->fresh()->status->value);
        $this->assertSame('active', $future->fresh()->status->value);
    }
}
