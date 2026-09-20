<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 8 contract (specs/16): OTP gate, lockout, idle + absolute timeout, step-up. */
class OtpSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function mk(string $email, string $role, array $over = []): User
    {
        $team = Team::firstOrCreate(['name' => 'Manila']);
        return User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id] + $over);
    }

    public function test_plain_rep_login_skips_otp(): void
    {
        $rep = $this->mk('rep.otp8@primepower.ph', 'sales_rep');
        $this->postJson('/api/v1/auth/login', ['email' => $rep->email, 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'user']]);
    }

    public function test_gated_roles_and_opted_in_users_get_challenge_then_tokens(): void
    {
        $admin = $this->mk('admin.otp8@primepower.ph', 'admin');
        $res = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.otp_required', true);
        $this->assertArrayNotHasKey('access_token', $res->json('data'));
        $challenge = $res->json('data.challenge_id');
        $this->assertNotNull($challenge);

        // Wrong code → 410, no tokens.
        $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => '000000'])->assertStatus(410);

        // Right code (mock) → tokens + session row.
        $tokens = $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => '123456'])
            ->assertOk()->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'user']]);
        $this->assertDatabaseHas('user_sessions', ['user_id' => $admin->id]);

        // Replay consumed code → 410.
        $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => '123456'])->assertStatus(410);
        $this->assertNotEmpty($tokens->json('data.access_token'));
    }

    public function test_expired_code_rejected(): void
    {
        $admin = $this->mk('admin.exp8@primepower.ph', 'admin');
        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        \App\Models\Otp::where('user_id', $admin->id)->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => '123456'])->assertStatus(410);
    }

    public function test_brute_force_locks_out_with_429_and_cooldown(): void
    {
        $admin = $this->mk('admin.lock8@primepower.ph', 'admin');
        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => '000000']);
        }
        // 6th attempt → 429 (specs/16 acceptance).
        $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => '000000'])->assertStatus(429);
        // Cooldown also blocks new sends.
        $t = auth('api')->login($admin);
        $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'login'], ['Authorization' => "Bearer $t"])->assertStatus(429);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'otp_locked']);
    }

    public function test_idle_timeout_rejects_and_active_use_survives(): void
    {
        $rep = $this->mk('rep.idle8@primepower.ph', 'sales_rep');
        $t = auth('api')->login($rep);

        // Active use bumps activity and passes.
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $t"])->assertOk();

        // Backdate past 5-min idle → next call 401 session_expired.
        \App\Models\UserSession::where('user_id', $rep->id)->update(['last_activity_at' => now()->subMinutes(6)]);
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $t"])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');

        // Refresh is rejected too (no silent renewal).
        $refresh = \Tymon\JWTAuth\Facades\JWTAuth::claims(['refresh' => true])->fromUser($rep);
        $this->postJson('/api/v1/auth/refresh', [], ['Authorization' => "Bearer $refresh"])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');
    }

    public function test_absolute_timeout_caps_at_12h(): void
    {
        $rep = $this->mk('rep.abs8@primepower.ph', 'sales_rep');
        $t = auth('api')->login($rep);
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $t"])->assertOk(); // creates row
        \App\Models\UserSession::where('user_id', $rep->id)->update([
            'created_at' => now()->subHours(13), 'last_activity_at' => now(),
        ]);
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $t"])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');
    }
}
