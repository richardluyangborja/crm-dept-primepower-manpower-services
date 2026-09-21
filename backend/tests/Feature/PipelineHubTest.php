<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pipeline hub children (specs/05 hub): staffing board + BI drilldown. */
class PipelineHubTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.hub@primepower.ph', 'manager'), 'rep' => $mk('rep.hub@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    protected function clientFor(User $owner, array $over = []): Client
    {
        return Client::create(array_merge([
            'owner_id' => $owner->id, 'name' => 'Hub Client '.uniqid(), 'status' => 'active',
            'last_contacted_at' => now(),
        ], $over));
    }

    public function test_staffing_lists_visible_clients_with_mock_and_totals(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $this->clientFor($o['rep']);

        $res = $this->getJson('/api/v1/staffing', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertNotEmpty($res['data']);
        $this->assertTrue($res['data'][0]['mock']);
        $this->assertArrayHasKey('deployed', $res['data'][0]);
        $this->assertArrayHasKey('total_deployed', $res['meta']);

        // Rep from another team sees none.
        $other = User::factory()->create(['email' => 'other.hub@primepower.ph', 'role' => 'sales_rep']);
        $ot = auth('api')->login($other);
        $this->getJson('/api/v1/staffing', ['Authorization' => "Bearer $ot"])->assertOk()
            ->assertJsonPath('data.data', []);
    }

    public function test_bi_breakdown_requires_manager_and_reconciles(): void
    {
        $o = $this->org();
        $rep = $o['rep'];
        $client = $this->clientFor($rep);
        Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $rep->id, 'title' => 'Hub deal',
            'stage' => 'won', 'value_centavos' => 100000, 'probability' => 100,
        ]);
        $tpl = SurveyTemplate::create(['name' => 'Hub NPS', 'type' => 'nps', 'questions' => [['q' => 'Q?']]]);
        $survey = Survey::create([
            'template_id' => $tpl->id, 'client_id' => $client->id, 'sent_by' => $rep->id,
            'token' => \Illuminate\Support\Str::random(32), 'status' => 'responded', 'due_at' => now()->addWeek(),
        ]);
        $survey->responses()->create(['score' => 9, 'comment' => 'Great', 'responded_at' => now()]);
        Invoice::create([
            'client_id' => $client->id, 'owner_id' => $rep->id, 'ref' => 'INV-HUB-1',
            'title' => 'Hub invoice', 'amount_centavos' => 50000, 'balance_centavos' => 50000,
            'status' => 'sent', 'due_at' => now()->addWeek()->toDateString(),
        ]);

        $rt = auth('api')->login($rep);
        $this->getJson('/api/v1/bi/client-breakdown', ['Authorization' => "Bearer $rt"])->assertForbidden();

        $mt = auth('api')->login($o['mgr']);
        $row = $this->getJson('/api/v1/bi/client-breakdown', ['Authorization' => "Bearer $mt"])
            ->assertOk()->json('data.data.0');
        $this->assertSame(1, $row['deals']);
        $this->assertSame(100000, $row['won_value_centavos']);
        $this->assertEquals(9, $row['nps_avg']);
        $this->assertSame(50000, $row['outstanding_centavos']);
        $this->assertSame('low', $row['risk']);
        $this->assertTrue($row['mock']);
    }
}
