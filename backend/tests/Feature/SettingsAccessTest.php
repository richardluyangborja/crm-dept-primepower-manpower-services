<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 6 contract (specs/09): access control, prefs, sessions, settings, exports. */
class SettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setupOrg(): array
    {
        $manila = Team::create(['name' => 'Manila']);
        $cebu = Team::create(['name' => 'Cebu']);
        $mk = fn ($email, $role, $team) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return [
            'super' => $mk('super.t6@primepower.ph', 'superadmin', $manila),
            'admin' => $mk('admin.t6@primepower.ph', 'admin', $manila),
            'mgr' => $mk('mgr.t6@primepower.ph', 'manager', $manila),
            'rep' => $mk('rep.t6@primepower.ph', 'sales_rep', $manila),
            'other' => $mk('other.t6@primepower.ph', 'sales_rep', $cebu),
            'manila' => $manila, 'cebu' => $cebu,
        ];
    }

    protected function token(User $u): string { return auth('api')->login($u); }

    /** Step-up grant via mock OTP (code 123456) for 428-gated actions. */
    protected function stepUpToken(string $accessToken): string
    {
        $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'step_up'], ['Authorization' => "Bearer $accessToken"])->assertCreated();
        return $this->postJson('/api/v1/auth/otp/verify', ['code' => '123456', 'purpose' => 'step_up'], ['Authorization' => "Bearer $accessToken"])
            ->assertOk()->json('data.step_up_token');
    }

    public function test_admin_invites_and_rep_forbidden_and_manager_scoped_readonly(): void
    {
        $o = $this->setupOrg();
        $at = $this->token($o['admin']);

        $id = $this->postJson('/api/v1/users', [
            'name' => 'New Rep', 'email' => 'new.rep@primepower.ph',
            'password' => 'Temporary123!', 'role' => 'sales_rep', 'team_id' => $o['manila']->id,
        ], ['Authorization' => "Bearer $at"])->assertCreated()->json('data.id');

        // rep cannot invite (team included so it reaches policy, not validation)
        $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@primepower.ph', 'password' => 'Temporary123!', 'role' => 'sales_rep', 'team_id' => $o['manila']->id,
        ], ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertForbidden();

        // manager sees only own team, cannot create
        $list = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$this->token($o['mgr'])])->assertOk()->json('data');
        $this->assertNotEmpty($list);
        foreach ($list as $row) $this->assertSame($o['manila']->id, $row['team_id']);
        $this->postJson('/api/v1/users', [
            'name' => 'Y', 'email' => 'y@primepower.ph', 'password' => 'Temporary123!', 'role' => 'sales_rep', 'team_id' => $o['manila']->id,
        ], ['Authorization' => 'Bearer '.$this->token($o['mgr'])])->assertForbidden();

        // invited user can log in
        $this->postJson('/api/v1/auth/login', ['email' => 'new.rep@primepower.ph', 'password' => 'Temporary123!'])->assertOk();
        User::find($id)->delete();
    }

    public function test_superadmin_hidden_and_role_creation_constrained(): void
    {
        $o = $this->setupOrg();
        // NOTE: auth('api')->login() caches the user on the guard for the
        // whole test, so re-login before every block that switches actors.
        $at = $this->token($o['admin']);

        // Admin list hides the superadmin; superadmin sees everyone.
        $adminList = $this->getJson('/api/v1/users?per_page=100', ['Authorization' => "Bearer $at"])->assertOk()->json('data');
        $this->assertNotEmpty($adminList);
        foreach ($adminList as $row) $this->assertNotSame('superadmin', $row['role']);
        $st = $this->token($o['super']);
        $superList = $this->getJson('/api/v1/users?per_page=100', ['Authorization' => "Bearer $st"])->assertOk()->json('data');
        $this->assertContains('superadmin', array_column($superList, 'role'));

        $mk = fn ($email, $role) => [
            'name' => $email, 'email' => $email, 'password' => 'Temporary123!',
            'role' => $role, 'team_id' => $o['manila']->id,
        ];
        // Admin cannot invite another admin (422); superadmin can invite admins, not superadmins.
        $at = $this->token($o['admin']);
        $this->postJson('/api/v1/users', $mk('a2@primepower.ph', 'admin'), ['Authorization' => "Bearer $at"])->assertStatus(422);
        $st = $this->token($o['super']);
        $this->postJson('/api/v1/users', $mk('a3@primepower.ph', 'admin'), ['Authorization' => "Bearer $st"])->assertCreated();
        $this->postJson('/api/v1/users', $mk('s2@primepower.ph', 'superadmin'), ['Authorization' => "Bearer $st"])->assertStatus(422);
        // Admin cannot promote anyone to admin either.
        $at = $this->token($o['admin']);
        $grant = $this->stepUpToken($at);
        $this->putJson("/api/v1/users/{$o['rep']->id}", ['role' => 'admin'], ['Authorization' => "Bearer $at", 'X-StepUp-Token' => $grant])->assertStatus(422);
    }

    public function test_self_and_last_superadmin_guards(): void
    {        $o = $this->setupOrg();
        $at = $this->token($o['admin']);

        $this->postJson("/api/v1/users/{$o['admin']->id}/deactivate", [], ['Authorization' => "Bearer $at"])->assertStatus(422);
        // Role change without step-up grant → 428; with grant → self-role guard (422).
        $this->putJson("/api/v1/users/{$o['admin']->id}", ['role' => 'sales_rep'], ['Authorization' => "Bearer $at"])->assertStatus(428);
        $grant = $this->stepUpToken($at);
        $this->putJson("/api/v1/users/{$o['admin']->id}", ['role' => 'sales_rep'], ['Authorization' => "Bearer $at", 'X-StepUp-Token' => $grant])->assertStatus(422);
        // Grant is single-use: replay → 428 again.
        $this->putJson("/api/v1/users/{$o['rep']->id}", ['role' => 'manager'], ['Authorization' => "Bearer $at", 'X-StepUp-Token' => $grant])->assertStatus(428);

        $st = $this->token($o['super']);
        $this->postJson("/api/v1/users/{$o['super']->id}/deactivate", [], ['Authorization' => "Bearer $st"])->assertStatus(422);

        // deactivating someone else works, and they can no longer log in
        $this->postJson("/api/v1/users/{$o['rep']->id}/deactivate", [], ['Authorization' => "Bearer $at"])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'password'])->assertUnauthorized();
    }

    public function test_superadmin_row_is_immutable(): void
    {
        $o = $this->setupOrg();
        $st = $this->token($o['super']);
        $sid = $o['super']->id;

        // Even the superadmin cannot mutate a superadmin row (except its own password).
        $this->putJson("/api/v1/users/$sid", ['name' => 'Hacked'], ['Authorization' => "Bearer $st"])->assertStatus(422);
        $this->postJson("/api/v1/users/$sid/deactivate", [], ['Authorization' => "Bearer $st"])->assertStatus(422);
        $this->postJson("/api/v1/users/$sid/reset-password", ['password' => 'NewPassword123!'], ['Authorization' => "Bearer $st"])->assertStatus(422);
        $this->assertTrue($o['super']->refresh()->is_active);

        // …but its own password change still works (lockout prevention).
        $this->postJson('/api/v1/me/password', [
            'current_password' => 'password', 'password' => 'BrandNewPass123!', 'password_confirmation' => 'BrandNewPass123!',
        ], ['Authorization' => "Bearer $st"])->assertOk();
    }

    public function test_deactivation_requires_successor_when_records_open(): void
    {
        $o = $this->setupOrg();
        $at = $this->token($o['admin']);
        $co = \App\Models\Company::create(['owner_id' => $o['rep']->id, 'name' => 'Handover Co']);
        \App\Models\Lead::create(['owner_id' => $o['rep']->id, 'company_id' => $co->id, 'company_name' => 'Handover Co', 'contact_name' => 'Ho Person', 'status' => 'new']);

        // Preview pinpoints the open records + a suggested successor.
        $prev = $this->getJson("/api/v1/users/{$o['rep']->id}/owned", ['Authorization' => "Bearer $at"])->assertOk()->json('data');
        $this->assertSame(1, $prev['open']['companies']);
        $this->assertSame(1, $prev['open']['leads']);
        $this->assertNotNull($prev['suggested_successor']);

        // No successor → 422 with counts.
        $this->postJson("/api/v1/users/{$o['rep']->id}/deactivate", [], ['Authorization' => "Bearer $at"])
            ->assertStatus(422)->assertJsonPath('meta.needs_successor', true);

        // With successor → everything open moves, account deactivates.
        $to = $prev['suggested_successor']['id'];
        $this->postJson("/api/v1/users/{$o['rep']->id}/deactivate", ['reassign_to' => $to], ['Authorization' => "Bearer $at"])->assertOk();
        $this->assertSame($to, $co->refresh()->owner_id);
        $this->assertFalse($o['rep']->refresh()->is_active);
    }

    public function test_reset_password_and_change_password(): void
    {        $o = $this->setupOrg();
        $at = $this->token($o['admin']);
        $this->postJson("/api/v1/users/{$o['rep']->id}/reset-password", ['password' => 'ResetPass123!'], ['Authorization' => "Bearer $at"])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'ResetPass123!'])->assertOk();

        $rt = $this->token($o['rep']->refresh());
        $this->postJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'], ['Authorization' => "Bearer $rt"])->assertStatus(422);
        $this->postJson('/api/v1/me/password', ['current_password' => 'ResetPass123!', 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'], ['Authorization' => "Bearer $rt"])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'NewPass12345'])->assertOk();
    }

    public function test_preferences_roundtrip(): void
    {
        $o = $this->setupOrg();
        $t = $this->token($o['rep']);
        $this->getJson('/api/v1/me/preferences', ['Authorization' => "Bearer $t"])->assertOk()->assertJsonPath('data.theme', 'system');
        $this->putJson('/api/v1/me/preferences', ['theme' => 'dark', 'notifications' => ['overdue' => false]], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.theme', 'dark')->assertJsonPath('data.notifications.overdue', false);
        // v2 tour flag persists (specs/18 §3B).
        $this->putJson('/api/v1/me/preferences', ['tour_seen' => true], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.tour_seen', true);
    }

    public function test_sessions_listed_revoked_and_logins_visible(): void
    {
        $o = $this->setupOrg();
        $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'password'])->assertOk();
        $t = $this->token($o['rep']);

        $sessions = $this->getJson('/api/v1/users-sessions', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertNotEmpty($sessions);
        // Revoking sessions kills their tokens: wipe all rows, same token rejected next.
        foreach ($sessions as $s) {
            $this->deleteJson("/api/v1/users-sessions/{$s['id']}", [], ['Authorization' => "Bearer $t"])->assertOk();
        }
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $t"])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');

        // Fresh login for the remaining assertions.
        $t2 = $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'password'])->assertOk()->json('data.access_token');
        $logins = $this->getJson('/api/v1/me/logins', ['Authorization' => "Bearer $t2"])->assertOk()->json('data');
        $this->assertNotEmpty($logins);
    }

    public function test_settings_superadmin_only_and_cached(): void
    {
        $o = $this->setupOrg();
        $this->getJson('/api/v1/settings', ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertOk();
        $this->putJson('/api/v1/settings', ['settings' => ['org_name' => 'X']], ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertForbidden();
        $this->putJson('/api/v1/settings', ['settings' => ['org_name' => 'Primepower Manpower Services', 'nope' => 1]], ['Authorization' => 'Bearer '.$this->token($o['super'])])
            ->assertOk()->assertJsonPath('data.saved', ['org_name']);
        $this->assertSame('Primepower Manpower Services', $this->getJson('/api/v1/settings', ['Authorization' => 'Bearer '.$this->token($o['super'])])->json('data.org_name'));
    }

    public function test_integrations_status_mock_only_and_csv_export(): void
    {
        $o = $this->setupOrg();
        $this->getJson('/api/v1/integrations/status', ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertForbidden();
        $st = $this->getJson('/api/v1/integrations/status', ['Authorization' => 'Bearer '.$this->token($o['admin'])])->assertOk()->json('data');
        $this->assertSame('mock', $st['mode']);
        $this->assertNotEmpty($st['services']);
        $this->putJson('/api/v1/integrations/mode', ['mode' => 'live'], ['Authorization' => 'Bearer '.$this->token($o['super'])])->assertStatus(422);

        $csv = $this->getJson('/api/v1/exports/clients.csv', ['Authorization' => 'Bearer '.$this->token($o['super'])])->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->getJson('/api/v1/exports/clients.csv', ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertForbidden();
        $this->getJson('/api/v1/exports/nope.csv', ['Authorization' => 'Bearer '.$this->token($o['super'])])->assertNotFound();
    }

    public function test_teams_listed_and_managed_by_admin(): void
    {
        $o = $this->setupOrg();
        $this->getJson('/api/v1/teams', ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertOk();
        $id = $this->postJson('/api/v1/teams', ['name' => 'Davao Test', 'region' => 'Davao Region'], ['Authorization' => 'Bearer '.$this->token($o['admin'])])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/teams', ['name' => 'Nope'], ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertForbidden();
        $this->putJson("/api/v1/teams/$id", ['region' => 'Davao del Sur'], ['Authorization' => 'Bearer '.$this->token($o['admin'])])->assertOk();
    }

    public function test_admins_are_teamless_and_sales_default_into_primepower_team(): void
    {
        $o = $this->setupOrg();
        $st = $this->token($o['super']);
        $salesTeam = \App\Models\Team::create(['name' => 'Primepower Team Test', 'region' => 'Nationwide']);

        // Admin invite with a team → team stripped to null.
        $adminId = $this->postJson('/api/v1/users', [
            'name' => 'A2', 'email' => 'a2@primepower.ph', 'password' => 'Temporary123!',
            'role' => 'admin', 'team_id' => $salesTeam->id,
        ], ['Authorization' => "Bearer $st"])->assertCreated()->json('data.id');
        $this->assertNull(\App\Models\User::find($adminId)->team_id);

        // Sales invite without a team → defaulted into Primepower Team (by name, others present).
        // (The migration already seeds it — firstOrCreate keeps this idempotent.)
        \App\Models\Team::firstOrCreate(['name' => 'Primepower Team'], ['region' => 'Nationwide']);
        $repId = $this->postJson('/api/v1/users', [
            'name' => 'R2', 'email' => 'r2@primepower.ph', 'password' => 'Temporary123!', 'role' => 'sales_rep',
        ], ['Authorization' => "Bearer $st"])->assertCreated()->json('data.id');
        $this->assertSame('Primepower Team', \App\Models\User::find($repId)->team->name);
    }

    public function test_teams_index_lists_only_the_primepower_team(): void
    {
        $o = $this->setupOrg();
        $st = $this->token($o['super']);
        // Run the Phase 1 convergence directly (migrations already ran at setup).
        $migration = require base_path('database/migrations/2026_09_26_000006_primepower_team.php');
        $migration->up();
        $names = $this->getJson('/api/v1/teams', ['Authorization' => "Bearer $st"])
            ->assertOk()->json('data.*.name');
        $this->assertSame(['Primepower Team'], $names);
        // Stragglers were re-homed, not orphaned.
        $this->assertSame(0, \App\Models\User::whereNull('team_id')->whereNotIn('role', ['superadmin', 'admin'])->count());
    }
}
