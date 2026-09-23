<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase H2: /hr endpoints are role-scoped, deterministic, and read-only. */
class HrEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $manila = Team::create(['name' => 'Manila']);
        $cebu = Team::create(['name' => 'Cebu']);
        $mk = fn ($email, $role, $team) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return [
            'super' => $mk('super.hr@primepower.ph', 'superadmin', $manila),
            'admin' => $mk('admin.hr@primepower.ph', 'admin', $manila),
            'mgr' => $mk('mgr.hr@primepower.ph', 'manager', $manila),
            'rep' => $mk('rep.hr@primepower.ph', 'sales_rep', $manila),
            'other' => $mk('other.hr@primepower.ph', 'sales_rep', $cebu),
        ];
    }

    protected function asUser(string $uri, User $u)
    {
        return $this->getJson($uri, ['Authorization' => 'Bearer '.auth('api')->login($u)]);
    }

    public function test_rep_sees_self_only(): void
    {
        $o = $this->org();
        $mine = $this->asUser("/api/v1/hr/performance?user_id={$o['rep']->id}", $o['rep'])->assertOk()->json('data');
        $this->assertTrue($mine['mock']);
        $this->assertArrayHasKey('composite', $mine);

        $this->asUser("/api/v1/hr/performance?user_id={$o['mgr']->id}", $o['rep'])->assertForbidden();
        $this->asUser("/api/v1/hr/attendance?user_id={$o['other']->id}", $o['rep'])->assertForbidden();
        // No user_id defaults to self.
        $this->asUser('/api/v1/hr/attendance', $o['rep'])->assertOk()->assertJsonPath('data.user_id', $o['rep']->id);
    }

    public function test_manager_sees_self_and_own_team(): void
    {
        $o = $this->org();
        $this->asUser("/api/v1/hr/attendance?user_id={$o['rep']->id}", $o['mgr'])->assertOk();
        $this->asUser("/api/v1/hr/leave?user_id={$o['mgr']->id}", $o['mgr'])->assertOk()
            ->assertJsonStructure(['data' => ['balances', 'leave_days']]);
        $this->asUser("/api/v1/hr/performance?user_id={$o['other']->id}", $o['mgr'])->assertForbidden();

        $dir = $this->asUser('/api/v1/hr/directory', $o['mgr'])->assertOk()->json('data');
        $this->assertNotEmpty($dir);
        foreach ($dir as $row) {
            $this->assertContains($row['role'], ['manager', 'sales_rep']);
        }
        $this->assertEmpty(array_filter($dir, fn ($r) => $r['email'] === 'other.hr@primepower.ph'));
    }

    public function test_admin_sees_all_but_has_no_record(): void
    {
        $o = $this->org();
        $this->asUser("/api/v1/hr/performance?user_id={$o['rep']->id}", $o['admin'])->assertOk();
        $this->asUser("/api/v1/hr/performance?user_id={$o['admin']->id}", $o['admin'])->assertNotFound();
        $this->asUser("/api/v1/hr/attendance?user_id={$o['super']->id}", $o['super'])->assertNotFound();

        $dir = $this->asUser('/api/v1/hr/directory', $o['admin'])->assertOk()->json('data');
        $emails = array_column($dir, 'email');
        $this->assertContains('rep.hr@primepower.ph', $emails);
        $this->assertContains('mgr.hr@primepower.ph', $emails);
        $this->assertNotContains('admin.hr@primepower.ph', $emails);
    }

    public function test_endpoints_are_read_only_and_shaped(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        foreach (['attendance', 'leave', 'performance', 'directory'] as $ep) {
            $this->postJson("/api/v1/hr/$ep", [], ['Authorization' => "Bearer $t"])->assertStatus(405);
        }
        $att = $this->asUser('/api/v1/hr/attendance?month=2026-08', $o['rep'])->assertOk()->json('data');
        $this->assertArrayHasKey('days', $att);
        $this->assertArrayHasKey('summary', $att);
    }
}
