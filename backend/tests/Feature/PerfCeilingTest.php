<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Performance ceilings on prod-shaped volume: bounded query counts and
 * wall-time budgets for the heaviest endpoints. Fails loudly if an
 * endpoint goes back to unbounded fetching or N+1 loops.
 */
class PerfCeilingTest extends TestCase
{
    use RefreshDatabase;

    protected function seedVolume(): User
    {
        $now = now();
        $admin = User::factory()->create(['email' => 'perf@primepower.ph', 'role' => 'superadmin']);
        $co = [];
        for ($i = 0; $i < 60; $i++) {
            $co[] = ['owner_id' => $admin->id, 'name' => "Perf Co $i", 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('companies')->insert($co);
        $coIds = DB::table('companies')->pluck('id')->all();

        $cl = [];
        foreach ($coIds as $i => $cid) {
            $cl[] = [
                'owner_id' => $admin->id, 'company_id' => $cid, 'name' => "Perf Client $i",
                'status' => $i % 9 === 0 ? 'inactive' : ($i % 3 === 0 ? 'prospect' : 'active'),
                'last_contacted_at' => $now->copy()->subDays($i % 60),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('clients')->insert($cl);
        $clIds = DB::table('clients')->pluck('id')->all();

        $stages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'contract', 'won', 'lost'];
        $opps = [];
        for ($i = 0; $i < 240; $i++) {
            $st = $stages[$i % 8];
            $created = $now->copy()->subDays($i % 365);
            $opps[] = [
                'client_id' => $clIds[$i % 60], 'company_id' => $coIds[$i % 60], 'owner_id' => $admin->id,
                'title' => "Perf deal $i", 'stage' => $st, 'value_centavos' => 10000000 + $i * 1000,
                'probability' => 50, 'headcount' => 10, 'rate_per_head_centavos' => 1500000, 'contract_months' => 12,
                'won_at' => $st === 'won' ? $created->copy()->addDays(20) : null,
                'lost_at' => $st === 'lost' ? $created->copy()->addDays(20) : null,
                'created_at' => $created, 'updated_at' => $created,
            ];
        }
        foreach (array_chunk($opps, 100) as $chunk) DB::table('opportunities')->insert($chunk);

        $types = ['call', 'email', 'meeting', 'site_visit', 'note'];
        $acts = [];
        for ($i = 0; $i < 600; $i++) {
            $acts[] = [
                'owner_id' => $admin->id, 'client_id' => $clIds[$i % 60], 'company_id' => $coIds[$i % 60],
                'type' => $types[$i % 5], 'subject' => "Perf touch $i", 'occurred_at' => $now->copy()->subDays($i % 60),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($acts, 200) as $chunk) DB::table('activities')->insert($chunk);

        $statuses = ['open', 'done', 'snoozed', 'overdue', 'escalated'];
        $fups = [];
        for ($i = 0; $i < 300; $i++) {
            $fups[] = [
                'owner_id' => $admin->id, 'client_id' => $clIds[$i % 60], 'company_id' => $coIds[$i % 60],
                'title' => "Perf reminder $i", 'due_at' => $now->copy()->subDays(($i % 90) - 30),
                'priority' => 'medium', 'status' => $statuses[$i % 5],
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($fups, 200) as $chunk) DB::table('followups')->insert($chunk);

        $tpl = DB::table('survey_templates')->insertGetId([
            'name' => 'Perf NPS', 'type' => 'nps', 'questions' => json_encode([]),
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sur = [];
        for ($i = 0; $i < 60; $i++) {
            $created = $now->copy()->subDays($i * 6);
            $sur[] = [
                'template_id' => $tpl, 'client_id' => $clIds[$i % 60], 'sent_by' => $admin->id,
                'channel' => 'link', 'token' => "perf-$i", 'status' => 'responded',
                'due_at' => $created->copy()->addDays(7), 'created_at' => $created, 'updated_at' => $created,
            ];
        }
        DB::table('surveys')->insert($sur);
        $surIds = DB::table('surveys')->pluck('id')->all();
        $res = [];
        foreach ($surIds as $i => $sid) {
            $res[] = [
                'survey_id' => $sid, 'score' => 4 + ($i % 7),
                'comment' => $i % 2 ? "Perf comment $i" : null,
                'responded_at' => $now->copy()->subDays(($i * 6) % 365),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('survey_responses')->insert($res);

        return $admin;
    }

    protected function measuredCall(string $method, string $uri, string $token): array
    {
        Cache::flush();
        $n = 0;
        DB::listen(function () use (&$n) { $n++; });
        $start = microtime(true);
        $res = $this->json($method, $uri, [], ['Authorization' => "Bearer $token"]);
        $ms = (microtime(true) - $start) * 1000;
        DB::flushQueryLog();

        return [$res, $n, $ms];
    }

    public function test_heavy_endpoints_stay_within_ceilings(): void
    {
        $admin = $this->seedVolume();
        $t = auth('api')->login($admin);

        [$res, $n, $ms] = $this->measuredCall('GET', '/api/v1/dashboard/summary', $t);
        $res->assertOk();
        $this->assertLessThan(40, $n, "dashboard queries: $n");
        $this->assertLessThan(10000, $ms, "dashboard ms: $ms");

        [$res, $n, $ms] = $this->measuredCall('GET', '/api/v1/reports/monthly', $t);
        $res->assertOk();
        $this->assertLessThan(70, $n, "pack queries: $n");
        $this->assertLessThan(20000, $ms, "pack ms: $ms");

        [$res, $n] = $this->measuredCall('GET', '/api/v1/opportunities/stage-counts', $t);
        $res->assertOk();
        $this->assertLessThan(5, $n, "stage-counts queries: $n");

        [$res, $n] = $this->measuredCall('GET', '/api/v1/hr/pulse?month='.now()->format('Y-m'), $t);
        $res->assertOk();
        $this->assertLessThan(15, $n, "pulse queries: $n");

        [$res, $n] = $this->measuredCall('GET', '/api/v1/opportunities?stage=new&page=1&per_page=7', $t);
        $res->assertOk();
        $this->assertLessThan(6, $n, "board page queries: $n");
    }

    public function test_pass_two_endpoints_stay_within_ceilings(): void
    {
        $admin = $this->seedVolume();
        $t = auth('api')->login($admin);

        [$res, $n] = $this->measuredCall('GET', '/api/v1/staffing?page=1&per_page=15', $t);
        $res->assertOk();
        $this->assertLessThan(10, $n, "staffing queries: $n");

        [$res, $n] = $this->measuredCall('GET', '/api/v1/finance/summary', $t);
        $res->assertOk();
        $this->assertLessThan(6, $n, "finance summary queries: $n");

        [$res, $n] = $this->measuredCall('GET', '/api/v1/surveys-analytics', $t);
        $res->assertOk();
        $this->assertLessThan(10, $n, "survey analytics queries: $n");

        [$res, $n] = $this->measuredCall('GET', '/api/v1/job-orders?status=deployed&per_page=12', $t);
        $res->assertOk();
        $this->assertLessThan(6, $n, "job board column queries: $n");
    }
}
