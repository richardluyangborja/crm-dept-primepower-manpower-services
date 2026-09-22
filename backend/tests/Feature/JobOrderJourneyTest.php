<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\JobOrder;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v2 journey A (specs/18 §3A): win persists a visible job order; timeline advances. */
class JobOrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.jo@primepower.ph', 'manager'), 'rep' => $mk('rep.jo@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    protected function oppFor(User $owner): Opportunity
    {
        $client = Client::create(['owner_id' => $owner->id, 'name' => 'JO Client '.uniqid(), 'status' => 'active']);
        return Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $owner->id, 'title' => 'Deal '.uniqid(),
            'stage' => 'negotiation', 'value_centavos' => 200000000, 'probability' => 80,
        ]);
    }

    protected function signFor(Opportunity $opp, string $token): void
    {
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract', 'headcount' => 40, 'rate_per_head_centavos' => 1500000,
            'contract_months' => 12, 'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $token"])->assertOk();
    }

    public function test_win_persists_job_order_row(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $opp = $this->oppFor($o['rep']);
        $this->signFor($opp, $t);

        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $t"])->assertOk();

        $jo = JobOrder::where('opportunity_id', $opp->id)->firstOrFail();
        $this->assertSame('draft', $jo->status);
        $this->assertStringStartsWith('JO-2026-', $jo->ref);
        $this->assertNotNull($jo->invoice_ref);
        $this->assertSame(40, $jo->headcount); // terms flow into the job order
        // Re-win is idempotent — no duplicate row (rep can't reopen: 403).
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", ['stage' => 'proposal', 'reopen_note' => 'x'], ['Authorization' => "Bearer $t"])->assertForbidden();
        $mgr = auth('api')->login($o['mgr']);
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", ['stage' => 'proposal', 'reopen_note' => 'Revived'], ['Authorization' => "Bearer $mgr"])->assertOk();
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $mgr"])->assertOk();
        $this->assertSame(1, JobOrder::where('opportunity_id', $opp->id)->count());
    }

    public function test_timeline_lists_and_advance_is_read_only(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $opp = $this->oppFor($o['rep']);
        $this->signFor($opp, $t);
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $t"])->assertOk();

        $list = $this->getJson("/api/v1/job-orders?client_id={$opp->client->opaqueId()}", ['Authorization' => "Bearer $t"])->assertOk();
        $this->assertCount(1, $list->json('data'));
        $id = $list->json('data.0.id');

        // Front-office guard (specs/04): the CRM never advances core execution.
        $this->postJson("/api/v1/job-orders/$id/advance", [], ['Authorization' => "Bearer $t"])
            ->assertForbidden()
            ->assertJsonPath('message', 'Job-order progression is handled by Client Management — the CRM shows read-only status.');
        $this->assertSame('draft', JobOrder::find(JobOrder::decodeId($id))->status);
    }

    public function test_operations_readbacks_are_mock_labeled_and_scoped(): void
    {
        $o = $this->org();
        $other = User::factory()->create(['email' => 'other.jo@primepower.ph', 'role' => 'sales_rep']);
        $opp = $this->oppFor($o['rep']);

        $res = $this->getJson("/api/v1/clients/{$opp->client->opaqueId()}/operations", ['Authorization' => 'Bearer '.auth('api')->login($o['rep'])])
            ->assertOk()->json('data');
        $this->assertTrue($res['mock']);
        $this->assertArrayHasKey('deployment', $res);
        $this->assertArrayHasKey('billing', $res);
        $this->assertSame(0, $res['job_orders']['count']);

        // Another rep's client is invisible.
        $this->getJson("/api/v1/clients/{$opp->client->opaqueId()}/operations", ['Authorization' => 'Bearer '.auth('api')->login($other)])
            ->assertForbidden();
        $this->getJson("/api/v1/job-orders?client_id={$opp->client->opaqueId()}", ['Authorization' => 'Bearer '.auth('api')->login($other)])
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
