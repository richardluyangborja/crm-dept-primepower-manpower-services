<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Followup;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Overhaul Phase 3: reminder ownership — assign on create, reassign with policy. */
class ReminderOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role, $teamId) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $teamId]);
        $mgr = $mk('mgr.ro@primepower.ph', 'manager', $team->id);
        $repA = $mk('rep.roa@primepower.ph', 'sales_rep', $team->id);
        $repB = $mk('rep.rob@primepower.ph', 'sales_rep', $team->id);
        $admin = $mk('admin.ro@primepower.ph', 'admin', null);

        return compact('mgr', 'repA', 'repB', 'admin');
    }

    protected function accountFor(User $owner): Client
    {
        $co = Company::create(['owner_id' => $owner->id, 'name' => 'Acct Co '.uniqid()]);

        return Client::create(['owner_id' => $owner->id, 'company_id' => $co->id, 'name' => 'Acct Client '.uniqid(), 'status' => 'active']);
    }

    protected function due(): string
    {
        return now()->addDay()->toIso8601String();
    }

    public function test_manager_assigns_reminder_to_rep(): void
    {
        $o = $this->org();
        $client = $this->accountFor($o['repA']);
        $t = auth('api')->login($o['mgr']);

        $this->postJson('/api/v1/followups', [
            'client_id' => $client->id, 'title' => 'Assigned task', 'due_at' => $this->due(), 'owner_id' => $o['repB']->id,
        ], ['Authorization' => "Bearer $t"])->assertCreated();

        $this->assertSame($o['repB']->id, Followup::latest('id')->first()->owner_id);
    }

    public function test_create_defaults_to_company_owner(): void
    {
        $o = $this->org();
        $client = $this->accountFor($o['repA']);
        $t = auth('api')->login($o['mgr']);

        $this->postJson('/api/v1/followups', [
            'client_id' => $client->id, 'title' => 'Default task', 'due_at' => $this->due(),
        ], ['Authorization' => "Bearer $t"])->assertCreated();

        $this->assertSame($o['repA']->id, Followup::latest('id')->first()->owner_id);
    }

    public function test_rep_is_locked_to_self(): void
    {
        $o = $this->org();
        $client = $this->accountFor($o['repA']);
        $t = auth('api')->login($o['repA']);

        $this->postJson('/api/v1/followups', [
            'client_id' => $client->id, 'title' => 'Mine', 'due_at' => $this->due(), 'owner_id' => $o['repB']->id,
        ], ['Authorization' => "Bearer $t"])->assertCreated();

        $this->assertSame($o['repA']->id, Followup::latest('id')->first()->owner_id);
    }

    public function test_owner_and_manager_can_reassign_stranger_cannot(): void
    {
        $o = $this->org();
        $client = $this->accountFor($o['repA']);
        $fup = Followup::create(['owner_id' => $o['repA']->id, 'client_id' => $client->id,
            'company_id' => $client->company_id, 'title' => 'Move me', 'due_at' => now()->addDay(), 'status' => 'open']);
        $url = "/api/v1/followups/{$fup->opaqueId()}";

        // Owner reassigns to a teammate.
        $tA = auth('api')->login($o['repA']);
        $this->putJson($url, ['owner_id' => $o['repB']->id], ['Authorization' => "Bearer $tA"])->assertOk();
        $this->assertSame($o['repB']->id, $fup->refresh()->owner_id);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'followups', 'entity_id' => $fup->id, 'action' => 'reassigned']);

        // Team manager moves it back.
        $tM = auth('api')->login($o['mgr']);
        $this->putJson($url, ['owner_id' => $o['repA']->id], ['Authorization' => "Bearer $tM"])->assertOk();
        $this->assertSame($o['repA']->id, $fup->refresh()->owner_id);

        // Stranger rep (other team, no company link) is blocked everywhere.
        $other = User::factory()->create(['email' => 'rep.rox@primepower.ph', 'role' => 'sales_rep']);
        $tX = auth('api')->login($other);
        $this->putJson($url, ['owner_id' => $other->id], ['Authorization' => "Bearer $tX"])->assertForbidden();
    }

    public function test_reassign_target_must_be_active_sales_role(): void
    {
        $o = $this->org();
        $client = $this->accountFor($o['repA']);
        $fup = Followup::create(['owner_id' => $o['repA']->id, 'client_id' => $client->id,
            'company_id' => $client->company_id, 'title' => 'Guard me', 'due_at' => now()->addDay(), 'status' => 'open']);
        $url = "/api/v1/followups/{$fup->opaqueId()}";
        $t = auth('api')->login($o['admin']);

        $off = User::factory()->create(['email' => 'rep.rooff@primepower.ph', 'role' => 'sales_rep', 'is_active' => false]);
        $this->putJson($url, ['owner_id' => $o['admin']->id], ['Authorization' => "Bearer $t"])->assertStatus(422);
        $this->putJson($url, ['owner_id' => $off->id], ['Authorization' => "Bearer $t"])->assertStatus(422);
    }
}
