<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private const PASSWORD = 'Test-Password-123!';

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_user_can_login_and_last_access_is_recorded(): void
    {
        $user = User::factory()->withRole('read-only')->create();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_audited_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'definitely-wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $log = AuditLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertSame($user->email, $log->user_email);
        $this->assertStringNotContainsString('definitely-wrong', json_encode($log->toArray()));
    }

    public function test_inactive_users_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_validation_errors_for_empty_fields(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_progressive_lockout_after_repeated_failures(): void
    {
        config(['vault.login.max_attempts' => 3, 'vault.login.base_lockout_seconds' => 60]);
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'bad'])->assertStatus(422);
        }

        // Aun con la contraseña correcta, el bloqueo está activo.
        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(429)->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_lockout_duration_doubles_on_each_lockout(): void
    {
        config(['vault.login.max_attempts' => 1, 'vault.login.base_lockout_seconds' => 60, 'vault.login.max_lockout_seconds' => 900]);
        $throttle = app(\App\Services\LoginThrottle::class);

        $throttle->hit('k');
        $first = $throttle->secondsUntilUnlocked('k');
        $this->travel(61)->seconds();
        $throttle->hit('k');
        $second = $throttle->secondsUntilUnlocked('k');

        $this->assertEqualsWithDelta(60, $first, 2);
        $this->assertEqualsWithDelta(120, $second, 2);
    }

    public function test_successful_login_clears_failures(): void
    {
        config(['vault.login.max_attempts' => 3]);
        $user = User::factory()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'bad']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'bad']);
        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'bad'])->assertStatus(422);
    }

    public function test_logout_is_audited_and_ends_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_deactivated_user_with_open_session_is_kicked_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_endpoint_returns_effective_permissions(): void
    {
        $user = $this->userWithRole('read-only');

        $this->actingAs($user)->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonFragment(['permissions' => $user->permissionSlugs()])
            ->assertJsonMissingPath('data.password');
    }
}
