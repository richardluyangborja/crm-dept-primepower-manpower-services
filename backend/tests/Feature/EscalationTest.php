<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Followup;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Overhaul Phase 4: escalation is rep-only, always lands on the team manager, visibly. */
class EscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role, $teamId) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $teamId]);
        $mgr = $mk('mgr.esc@primepower.ph', 'manager', $team->id);
        $repA = $mk('rep.esca@primepower.ph', 'sales_rep', $team->id);
        $repB = $mk('rep.escb@primepower.ph', 'sales_rep', $team->id);
        $admin = $mk('admin.esc@primepower.ph', 'admin', null);

        return compact('mgr', 'repA', 'repB', 'admin');
    }

    protected function overdueFor(User $owner): Followup
    {
        $co = Company::create(['owner_id' => $owner->id, 'name' => 'Esc Co '.uniqid()]);
        $client = Client::create(['owner_id' => $owner->id, 'company_id' => $co->id, 'name' => 'Esc Client '.uniqid(), 'status' => 'active']);

        return Followup::create(['owner_id' => $owner->id, 'client_id' => $client->id,
            'company_id' => $co->id, 'title' => 'Late task', 'due_at' => now()->subDay(), 'status' => 'overdue']);
    }

    public function test_rep_escalates_own_reminder_to_team_manager(): void
    {
        $o = $this->org();
        $fup = $this->overdueFor($o['repA']);
        $t = auth('api')->login($o['repA']);

        $this->postJson("/api/v1/followups/{$fup->opaqueId()}/escalate", [], ['Authorization' => "Bearer $t"])
            ->assertOk()
            ->assertJsonPath('data.status', 'escalated')
            ->assertJsonPath('data.escalated_to', $o['mgr']->id)
            ->assertJsonPath('data.escalated_to_name', $o['mgr']->name);

        $this->assertDatabaseHas('audit_logs', ['entity' => 'followups', 'entity_id' => $fup->id, 'action' => 'escalated']);
        $this->assertDatabaseHas('notifications', ['user_id' => $o['mgr']->id, 'type' => 'escalation']);
    }

    public function test_only_the_owning_rep_can_escalate(): void
    {
        $o = $this->org();
        $fup = $this->overdueFor($o['repA']);
        $url = "/api/v1/followups/{$fup->opaqueId()}/escalate";

        foreach ([$o['mgr'], $o['admin'], $o['repB']] as $actor) {
            $t = auth('api')->login($actor);
            $this->postJson($url, [], ['Authorization' => "Bearer $t"])->assertForbidden();
        }
        $this->assertSame('overdue', $fup->refresh()->status);
    }

    public function test_escalation_without_a_manager_fails_loudly(): void
    {
        $o = $this->org();
        $lone = User::factory()->create(['email' => 'rep.lone@primepower.ph', 'role' => 'sales_rep', 'team_id' => null]);
        $fup = $this->overdueFor($lone);
        $t = auth('api')->login($lone);

        $this->postJson("/api/v1/followups/{$fup->opaqueId()}/escalate", [], ['Authorization' => "Bearer $t"])
            ->assertStatus(422);
    }
}
