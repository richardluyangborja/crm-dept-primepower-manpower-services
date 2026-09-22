<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pipeline↔finance refinement: per-head terms, contract signing, monthly first invoice. */
class PipelineFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.pf@primepower.ph', 'manager'), 'rep' => $mk('rep.pf@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    protected function oppFor(User $owner, array $over = []): Opportunity
    {
        $client = Client::create(['owner_id' => $owner->id, 'name' => 'PF Client '.uniqid(), 'status' => 'active']);
        return Opportunity::create(array_merge([
            'client_id' => $client->id, 'owner_id' => $owner->id, 'title' => 'Deal '.uniqid(),
            'stage' => 'negotiation', 'value_centavos' => 0, 'probability' => 80,
        ], $over));
    }

    protected function token(User $u): string { return auth('api')->login($u); }

    protected function signFor(Opportunity $opp, string $token): void
    {
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract',
            'headcount' => $opp->headcount ?? 40,
            'rate_per_head_centavos' => $opp->rate_per_head_centavos ?? 1500000,
            'contract_months' => $opp->contract_months ?? 12,
            'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $token"])->assertOk();
    }

    public function test_signing_requires_terms_and_creates_contract(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $opp = $this->oppFor($o['rep']);

        // Missing terms → 422.
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", ['stage' => 'contract'], ['Authorization' => "Bearer $t"])
            ->assertStatus(422);

        $res = $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract',
            'headcount' => 40, 'rate_per_head_centavos' => 3500000, 'contract_months' => 12,
            'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $t"])->assertOk();

        $this->assertSame(140000000, $res->json('data.monthly_billing_centavos'));
        $this->assertSame(1680000000, $res->json('data.contract_total_centavos'));

        $contract = Contract::where('opportunity_id', $opp->id)->firstOrFail();
        $this->assertSame('CTR-2026-'.str_pad((string) $opp->id, 4, '0', STR_PAD_LEFT), $contract->ref);
        $this->assertSame('active', $contract->status);

        // Re-signing updates terms, never duplicates.
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract', 'headcount' => 50,
            'rate_per_head_centavos' => 3500000, 'contract_months' => 12,
            'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $t"])->assertOk();
        $this->assertSame(1, Contract::where('opportunity_id', $opp->id)->count());
        $this->assertSame(50, Contract::where('opportunity_id', $opp->id)->first()->headcount);
    }

    public function test_win_issues_first_monthly_invoice(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $opp = $this->oppFor($o['rep'], [
            'headcount' => 20, 'rate_per_head_centavos' => 4000000, 'contract_months' => 6,
        ]);

        $this->signFor($opp, $t);
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $t"])->assertOk();
        $inv = Invoice::where('opportunity_id', $opp->id)->firstOrFail();
        $this->assertSame(80000000, $inv->amount_centavos); // one month, not the contract total
        $this->assertSame('sent', $inv->status);
        $this->assertStringContainsString('month 1', $inv->title);
    }

    public function test_win_requires_signed_contract(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $opp = $this->oppFor($o['rep']);
        $opp->update(['value_centavos' => 500000]);

        // No contract → 422, no invoice, no fallback.
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $t"])
            ->assertStatus(422);
        $this->assertSame(0, Invoice::where('opportunity_id', $opp->id)->count());

        // Sign, then win works.
        $this->signFor($opp, $t);
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $t"])->assertOk();
        $this->assertSame(1, Invoice::where('opportunity_id', $opp->id)->count());
    }

    public function test_contract_list_scoped_and_shaped(): void
    {
        $o = $this->org();
        $other = User::factory()->create(['email' => 'other.pf@primepower.ph', 'role' => 'sales_rep']);
        $opp = $this->oppFor($o['rep']);
        $t = $this->token($o['rep']);
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract', 'headcount' => 10,
            'rate_per_head_centavos' => 3000000, 'contract_months' => 12,
            'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $t"])->assertOk();

        $mine = $this->getJson('/api/v1/contracts', ['Authorization' => "Bearer $t"])->assertOk();
        $this->assertCount(1, $mine->json('data'));
        $this->assertArrayHasKey('monthly_billing_centavos', $mine->json('data.0'));

        $theirs = $this->getJson('/api/v1/contracts', ['Authorization' => 'Bearer '.$this->token($other)])->assertOk();
        $this->assertCount(0, $theirs->json('data'));
    }
}
