<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\Insights\NextBestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Front-office reframe (specs/04): fulfillment rollup, 4 insight rules, advance guard. */
class FrontOfficeTest extends TestCase
{
    use RefreshDatabase;

    protected function rep(): array
    {
        $team = Team::create(['name' => 'Front']);
        $rep = User::factory()->create(['email' => 'rep.front@primepower.ph', 'role' => 'sales_rep', 'team_id' => $team->id]);
        return [$rep, $team];
    }

    protected function clientFor(User $owner): Client
    {
        return Client::create([
            'owner_id' => $owner->id, 'name' => 'Front Client '.uniqid(),
            'status' => 'active', 'last_contacted_at' => now(),
        ]);
    }

    protected function oppFor(User $owner, Client $client): Opportunity
    {
        return Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $owner->id, 'title' => 'Front deal '.uniqid(),
            'stage' => 'won', 'value_centavos' => 100000, 'probability' => 100,
        ]);
    }

    protected function kinds(Client $client): array
    {
        return array_column(NextBestAction::forClient($client->refresh()), 'kind');
    }

    protected function contractFor(User $owner, Client $client, array $over = []): Contract
    {
        $opp = $this->oppFor($owner, $client);
        return Contract::create(array_merge([
            'opportunity_id' => $opp->id, 'client_id' => $client->id, 'owner_id' => $owner->id,
            'ref' => 'CTR-FRONT-'.uniqid(), 'title' => 'Front contract',
            'headcount' => 50, 'rate_per_head_centavos' => 100000, 'contract_months' => 12,
            'monthly_billing_centavos' => 5000000, 'contract_total_centavos' => 60000000,
            'start_date' => now()->toDateString(), 'status' => 'active',
        ], $over));
    }

    public function test_staffing_gap_and_fulfillment_rollup(): void
    {
        [$rep] = $this->rep();
        $client = $this->clientFor($rep);
        $this->contractFor($rep, $client);
        // ABC story from the brief: 50 required, 42 deployed, 8 remain.
        app()->bind(\App\Services\Contracts\WorkforceServiceInterface::class, fn () => new class implements \App\Services\Contracts\WorkforceServiceInterface {
            public function headcountByClient(int $clientId): array
            {
                return ['deployed' => 42, 'site' => 'Test site', 'mock' => true];
            }
        });

        $t = auth('api')->login($rep);
        $ful = $this->getJson("/api/v1/clients/{$client->opaqueId()}/operations", ['Authorization' => "Bearer $t"])
            ->assertOk()->json('data.fulfillment');
        $this->assertSame(50, $ful['required']);
        $this->assertSame(42, $ful['deployed']);
        $this->assertSame(8, $ful['remaining']);
        $this->assertSame(84, $ful['pct']);
        $this->assertSame('partially_fulfilled', $ful['status']);
        $this->assertTrue($ful['mock']);
        $this->assertContains('staffing_gap', $this->kinds($client));
    }

    public function test_contract_renewal_rule(): void
    {
        [$rep] = $this->rep();
        $client = $this->clientFor($rep);
        $this->contractFor($rep, $client, [
            'title' => 'Expiring contract', 'headcount' => 10,
            'monthly_billing_centavos' => 1000000, 'contract_total_centavos' => 12000000,
            'start_date' => now()->subMonths(11)->toDateString(),
        ]);

        $this->assertContains('contract_renewal', $this->kinds($client));
    }

    public function test_satisfaction_drop_rule(): void
    {
        [$rep] = $this->rep();
        $client = $this->clientFor($rep);
        $tpl = SurveyTemplate::create(['name' => 'Front NPS', 'type' => 'nps', 'questions' => [['q' => 'Q?']]]);
        $mk = fn ($score, $daysAgo) => tap(Survey::create([
            'template_id' => $tpl->id, 'client_id' => $client->id, 'sent_by' => $rep->id,
            'token' => Str::random(32), 'status' => 'responded', 'due_at' => now()->addWeek(),
        ]), fn ($s) => $s->responses()->create([
            'score' => $score, 'responded_at' => now()->subDays($daysAgo),
        ]));
        $mk(9, 20);
        $mk(5, 1);

        $this->assertContains('satisfaction_drop', $this->kinds($client));
    }

    public function test_expansion_rule(): void
    {
        [$rep] = $this->rep();
        $client = $this->clientFor($rep);
        Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $rep->id, 'title' => 'Old deal',
            'stage' => 'won', 'value_centavos' => 100000, 'probability' => 100,
        ]);
        Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $rep->id, 'title' => 'New warehouse need',
            'stage' => 'contacted', 'value_centavos' => 50000, 'probability' => 20,
        ]);

        $this->assertContains('expansion', $this->kinds($client));
    }

    public function test_advance_endpoint_is_read_only(): void
    {
        [$rep] = $this->rep();
        $t = auth('api')->login($rep);
        $client = $this->clientFor($rep);
        $opp = Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $rep->id, 'title' => 'Front deal',
            'stage' => 'negotiation', 'value_centavos' => 100000, 'probability' => 80,
        ]);
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract', 'headcount' => 10, 'rate_per_head_centavos' => 100000,
            'contract_months' => 12, 'start_date' => now()->toDateString(),
        ], ['Authorization' => "Bearer $t"])->assertOk();
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", [], ['Authorization' => "Bearer $t"])->assertOk();
        $joId = \App\Models\JobOrder::where('opportunity_id', $opp->id)->firstOrFail()->opaqueId();

        $this->postJson("/api/v1/job-orders/$joId/advance", [], ['Authorization' => "Bearer $t"])->assertForbidden();
        $this->assertSame('draft', \App\Models\JobOrder::find(\App\Models\JobOrder::decodeId($joId))->status);
    }
}
