<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 2 contract (specs/05): kanban data, guarded moves, win/loss with mock docs. */
class OpportunityPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function rep(string $email): User
    {
        return User::factory()->create(['email' => $email, 'role' => 'sales_rep']);
    }

    protected function clientFor(User $owner): Client
    {
        return Client::create([
            'owner_id' => $owner->id, 'name' => 'Test Client Co.', 'status' => 'active',
        ]);
    }

    public function test_create_applies_stage_probability_and_lists_by_stage(): void
    {
        $rep = $this->rep('rep.pipe@primepower.ph');
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/opportunities', [
            'client_id' => $this->clientFor($rep)->id,
            'title' => '40 crew — BGC', 'value_centavos' => 120000000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()
            ->assertJsonPath('data.stage', 'new')
            ->assertJsonPath('data.probability', 10)
            ->assertJsonPath('data.weighted_centavos', 12000000)
            ->json('data.id');

        $list = $this->getJson('/api/v1/opportunities?stage=new', ['Authorization' => "Bearer $t"])->assertOk();
        $this->assertSame($id, $list->json('data.0.id'));
    }

    public function test_move_updates_probability_and_lost_requires_reason(): void
    {
        $rep = $this->rep('rep.move@primepower.ph');
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/opportunities', [
            'client_id' => $this->clientFor($rep)->id, 'title' => 'Move me', 'value_centavos' => 100000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'proposal'], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.probability', 60);

        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'lost'], ['Authorization' => "Bearer $t"])
            ->assertStatus(422);
        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'lost', 'lost_reason' => 'Budget frozen'], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.lost_reason', 'Budget frozen');
    }

    public function test_qualifying_requires_value_and_terms(): void
    {
        $rep = $this->rep('rep.qual@primepower.ph');
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/opportunities', [
            'client_id' => $this->clientFor($rep)->id, 'title' => 'Qualify me', 'value_centavos' => 100000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');

        // Value present but no manpower terms → 422.
        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'qualified'], ['Authorization' => "Bearer $t"])
            ->assertStatus(422);

        // Set terms via update, then qualify works.
        $oppInt = \App\Models\Opportunity::decodeId($id);
        \App\Models\Opportunity::where('id', $oppInt)->update([
            'headcount' => 20, 'rate_per_head_centavos' => 500000, 'contract_months' => 12,
        ]);
        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'qualified'], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.stage', 'qualified');
    }

    public function test_win_creates_mock_job_order_and_invoice_audit(): void
    {
        $rep = $this->rep('rep.win@primepower.ph');
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/opportunities', [
            'client_id' => $this->clientFor($rep)->id, 'title' => 'Win me', 'value_centavos' => 50000000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/opportunities/$id/move", [
            'stage' => 'contract', 'headcount' => 40, 'rate_per_head_centavos' => 1500000,
            'contract_months' => 12, 'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $t"])->assertOk();
        $this->postJson("/api/v1/opportunities/$id/win", [], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.stage', 'won');

        $log = \App\Models\AuditLog::where('entity', 'opportunities')->where('action', 'stage_moved')->latest('id')->first();
        $intId = \App\Models\Opportunity::decodeId($id);
        $this->assertSame('JO-2026-'.str_pad((string) $intId, 4, '0', STR_PAD_LEFT), $log->meta['job_order']['job_order_ref']);
        $this->assertSame('INV-2026-'.str_pad((string) $intId, 4, '0', STR_PAD_LEFT), $log->meta['invoice']['invoice_ref']);
        $this->assertDatabaseHas('notifications', ['user_id' => $rep->id, 'type' => 'won']);
    }

    public function test_terminal_reopen_is_manager_only_with_note(): void
    {
        $rep = $this->rep('rep.closed@primepower.ph');
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/opportunities', [
            'client_id' => $this->clientFor($rep)->id, 'title' => 'Closed deal', 'value_centavos' => 100000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/opportunities/$id/lose", ['lost_reason' => 'Timing'], ['Authorization' => "Bearer $t"])->assertOk();

        // Rep cannot reopen…
        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'negotiation', 'reopen_note' => 'Back on'], ['Authorization' => "Bearer $t"])
            ->assertForbidden();
        // …manager of the same team can, with a note.
        $team = \App\Models\Team::create(['name' => 'Reopen Team']);
        $rep->update(['team_id' => $team->id]);
        $mgr = User::factory()->create(['email' => 'mgr.reopen@primepower.ph', 'role' => 'manager', 'team_id' => $team->id]);
        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'negotiation'], ['Authorization' => 'Bearer '.auth('api')->login($mgr)])
            ->assertStatus(422); // note required
        $this->postJson("/api/v1/opportunities/$id/move", ['stage' => 'negotiation', 'reopen_note' => 'Client revived budget'], ['Authorization' => 'Bearer '.auth('api')->login($mgr)])
            ->assertOk()->assertJsonPath('data.stage', 'negotiation');
    }

    public function test_update_rejects_stage_field(): void
    {
        $rep = $this->rep('rep.nostage@primepower.ph');
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/opportunities', [
            'client_id' => $this->clientFor($rep)->id, 'title' => 'No shortcut', 'value_centavos' => 100000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/opportunities/$id", ['stage' => 'won'], ['Authorization' => "Bearer $t"])->assertStatus(422);
    }
}
