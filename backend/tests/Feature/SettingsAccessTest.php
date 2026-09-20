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

    public function test_self_and_last_superadmin_guards(): void
    {
        $o = $this->setupOrg();
        $at = $this->token($o['admin']);

        $this->postJson("/api/v1/users/{$o['admin']->id}/deactivate", [], ['Authorization' => "Bearer $at"])->assertStatus(422);
        $this->putJson("/api/v1/users/{$o['admin']->id}", ['role' => 'sales_rep'], ['Authorization' => "Bearer $at"])->assertStatus(422);

        $st = $this->token($o['super']);
        $this->postJson("/api/v1/users/{$o['super']->id}/deactivate", [], ['Authorization' => "Bearer $st"])->assertStatus(422);

        // deactivating someone else works, and they can no longer log in
        $this->postJson("/api/v1/users/{$o['rep']->id}/deactivate", [], ['Authorization' => "Bearer $at"])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'password'])->assertUnauthorized();
    }

    public function test_reset_password_and_change_password(): void
    {
        $o = $this->setupOrg();
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
    }

    public function test_sessions_listed_revoked_and_logins_visible(): void
    {
        $o = $this->setupOrg();
        $this->postJson('/api/v1/auth/login', ['email' => $o['rep']->email, 'password' => 'password'])->assertOk();
        $t = $this->token($o['rep']);

        $sessions = $this->getJson('/api/v1/users-sessions', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertNotEmpty($sessions);
        $sid = $sessions[0]['id'];
        $this->deleteJson("/api/v1/users-sessions/$sid", [], ['Authorization' => "Bearer $t"])->assertOk();
        $this->assertDatabaseHas('user_sessions', ['id' => $sid]);

        $logins = $this->getJson('/api/v1/me/logins', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertNotEmpty($logins);
    }

    public function test_settings_superadmin_only_and_cached(): void
    {
        $o = $this->setupOrg();
        $this->getJson('/api/v1/settings', ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertOk();
        $this->putJson('/api/v1/settings', ['settings' => ['org_name' => 'X']], ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertForbidden();
        $this->putJson('/api/v1/settings', ['settings' => ['org_name' => 'PrimePower Manpower Services', 'nope' => 1]], ['Authorization' => 'Bearer '.$this->token($o['super'])])
            ->assertOk()->assertJsonPath('data.saved', ['org_name']);
        $this->assertSame('PrimePower Manpower Services', $this->getJson('/api/v1/settings', ['Authorization' => 'Bearer '.$this->token($o['super'])])->json('data.org_name'));
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
}
