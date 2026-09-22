<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use App\Services\Insights\SentimentAnalyzer;
use App\Services\Insights\WinProbability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 7 contract (specs/15): insights, packs, generate, feedback. */
class AiReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.ai@primepower.ph', 'manager'), 'rep' => $mk('rep.ai@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    protected function clientFor(User $owner, array $over = []): Client
    {
        return Client::create(array_merge(['owner_id' => $owner->id, 'name' => 'Risk Client '.uniqid(), 'status' => 'active'], $over));
    }

    public function test_dashboard_summary_has_ai_widgets(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['mgr']);
        $this->getJson('/api/v1/dashboard/summary', ['Authorization' => "Bearer $t"])->assertOk()
            ->assertJsonStructure(['data' => [
                'forecast', 'by_stage', 'nps_avg', 'at_risk', 'leaderboard',
                'win_rate_90d', 'next_best_actions', 'meta',
                'trends' => ['monthly'],
            ]])
            ->assertJsonPath('data.forecast.ai_adjusted_centavos', fn ($v) => is_int($v))
            ->assertJsonPath('data.meta.ai_preview', true);
    }

    public function test_client_insight_returns_risk_drivers_and_nba(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['mgr']);
        // High-risk: inactive + stale contact.
        $c = $this->clientFor($o['rep'], ['status' => 'inactive', 'last_contacted_at' => now()->subDays(45)]);
        $this->getJson("/api/v1/insights/clients/{$c->opaqueId()}", ['Authorization' => "Bearer $t"])->assertOk()
            ->assertJsonPath('data.level', 'high')
            ->assertJsonPath('data.nba.0.kind', 'client_stale')
            ->assertJsonPath('data.ai_preview', true);
    }

    public function test_opp_insight_applies_recency_multiplier(): void
    {
        $o = $this->org();
        $c = $this->clientFor($o['rep']);
        $opp = Opportunity::create([
            'client_id' => $c->id, 'owner_id' => $o['rep']->id, 'title' => 'Stale deal',
            'stage' => 'proposal', 'value_centavos' => 100000, 'probability' => 60,
        ]);
        // Eloquent manages timestamps on create — backdate via query builder.
        \Illuminate\Support\Facades\DB::table('opportunities')->where('id', $opp->id)
            ->update(['updated_at' => now()->subDays(45), 'created_at' => now()->subDays(60)]);
        // 60 × 0.85 (stale) = 51
        $this->assertSame(51, WinProbability::forOpp($opp->refresh())['probability']);

        $t = auth('api')->login($o['mgr']);
        $this->getJson("/api/v1/insights/opportunities/{$opp->opaqueId()}", ['Authorization' => "Bearer $t"])->assertOk()
            ->assertJsonPath('data.probability', 51)
            ->assertJsonPath('data.ai_preview', true);
    }

    public function test_sentiment_keywords(): void
    {
        $this->assertSame('positive', SentimentAnalyzer::analyze('Mabilis ang deployment, salamat!')['label']);
        $this->assertSame('negative', SentimentAnalyzer::analyze('Delivery delayed, poor coordination')['label']);
        $this->assertSame('neutral', SentimentAnalyzer::analyze('Noted on the request')['label']);
    }

    public function test_reports_weekly_monthly_and_generate(): void
    {
        $o = $this->org();
        $rep = $this->token($o['rep']);
        $this->getJson('/api/v1/reports/weekly', ['Authorization' => "Bearer $rep"])->assertForbidden();

        $mt = auth('api')->login($o['mgr']);
        $this->getJson('/api/v1/reports/weekly', ['Authorization' => "Bearer $mt"])->assertOk()
            ->assertJsonStructure(['data' => ['narrative', 'tables', 'meta']])
            ->assertJsonPath('data.meta.ai_preview', true);
        $this->getJson('/api/v1/reports/monthly', ['Authorization' => "Bearer $mt"])->assertOk()
            ->assertJsonPath('data.tables.forecast.open_count', fn ($v) => is_int($v));

        $id = $this->postJson('/api/v1/reports/generate', ['type' => 'weekly'], ['Authorization' => "Bearer $mt"])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('reports', ['id' => $id, 'type' => 'weekly']);
        $this->getJson('/api/v1/reports', ['Authorization' => "Bearer $mt"])->assertOk();
        $this->postJson('/api/v1/reports/generate', ['type' => 'daily'], ['Authorization' => "Bearer $mt"])->assertStatus(422);
    }

    public function test_feedback_validates_and_stores(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $this->postJson('/api/v1/insights/feedback', ['insight_key' => 'x', 'rating' => 'maybe'], ['Authorization' => "Bearer $t"])->assertStatus(422);
        $this->postJson('/api/v1/insights/feedback', ['insight_key' => 'at_risk:1', 'rating' => 'down', 'note' => 'Already contacted'], ['Authorization' => "Bearer $t"])
            ->assertCreated();
        $this->assertDatabaseHas('insight_feedback', ['insight_key' => 'at_risk:1', 'rating' => 'down']);
    }

    public function test_dashboard_trends_bucket_by_month(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['mgr']);
        $client = $this->clientFor($o['rep']);
        // Note: created_at/updated_at are backdated via query builder because
        // Eloquent::create() overwrites explicit timestamps.
        $backdate = fn ($id, $daysAgo) => \Illuminate\Support\Facades\DB::table('opportunities')
            ->where('id', $id)->update(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)]);
        $mkOpp = function ($stage, $daysAgo, $extra = []) use ($client, $o, $backdate) {
            $opp = Opportunity::create(array_merge([
                'client_id' => $client->id, 'owner_id' => $o['rep']->id, 'title' => 'Trend '.uniqid(),
                'stage' => $stage, 'value_centavos' => 100000, 'probability' => 50,
            ], $extra));
            $backdate($opp->id, $daysAgo);

            return $opp->refresh();
        };
        $won = $mkOpp('won', 70, ['won_at' => now()->subDays(40)]);
        $lost = $mkOpp('lost', 75, ['lost_at' => now()->subDays(45)]);
        $mkOpp('proposal', 5);

        $trend = $this->getJson('/api/v1/dashboard/summary', ['Authorization' => "Bearer $t"])
            ->assertOk()->json('data.trends.monthly');
        $this->assertCount(6, $trend);
        $this->assertSame(now()->format('Y-m'), $trend[5]['month']);
        $this->assertSame(1, $trend[5]['new_opps']);
        $byMonth = collect($trend)->keyBy('month');
        $wm = $won->refresh()->won_at->format('Y-m');
        $lm = $lost->refresh()->lost_at->format('Y-m');
        $this->assertSame(1, $byMonth[$wm]['won']);
        $expectRate = $wm === $lm ? 50 : 100;
        $this->assertEquals($expectRate, $byMonth[$wm]['win_rate']);
        if ($wm !== $lm) {
            $this->assertEquals(0, $byMonth[$lm]['win_rate']);
        }

        // A rep from another team sees none of it (scope respected).
        $outsider = User::factory()->create(['email' => 'out.trend@primepower.ph', 'role' => 'sales_rep']);
        $ot = auth('api')->login($outsider);
        $other = $this->getJson('/api/v1/dashboard/summary', ['Authorization' => "Bearer $ot"])->assertOk()->json('data.trends.monthly');
        $this->assertSame(0, array_sum(array_column($other, 'won')));
    }

    protected function token(User $u): string { return auth('api')->login($u); }
}
