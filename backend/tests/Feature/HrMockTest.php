<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Followup;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use App\Services\Contracts\AttendanceServiceInterface;
use App\Services\Contracts\PerformanceServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase H1: Core-2 mocks are deterministic, bucketed, and blend real CRM output. */
class HrMockTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_is_deterministic_and_bucketed(): void
    {
        $svc = app(AttendanceServiceInterface::class);
        $a = $svc->monthly(7, '2026-08');
        $b = $svc->monthly(7, '2026-08');
        $this->assertSame($a, $b);

        $s = $a['summary'];
        $this->assertSame($s['workdays'], $s['present'] + $s['late'] + $s['absent'] + $s['leave']);
        $this->assertGreaterThan(0, $s['workdays']);
        $this->assertTrue($a['mock']);
        $this->assertArrayHasKey('vacation', $a['leave']);
        $this->assertArrayHasKey('sick', $a['leave']);

        // Different users get different patterns; bad months return empties.
        $this->assertNotSame($a['days'], $svc->monthly(8, '2026-08')['days']);
        $this->assertSame([], $svc->monthly(7, 'nope')['days']);
    }

    public function test_performance_blends_real_crm_output(): void
    {
        $team = Team::create(['name' => 'HR']);
        $rep = User::factory()->create(['email' => 'rep.hr@primepower.ph', 'role' => 'sales_rep', 'team_id' => $team->id]);
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'HR Client', 'status' => 'active']);
        Opportunity::create([
            'client_id' => $client->id, 'owner_id' => $rep->id, 'title' => 'HR deal',
            'stage' => 'won', 'value_centavos' => 25000000, 'probability' => 100,
            'won_at' => now()->subDays(5),
        ]);
        Activity::create(['owner_id' => $rep->id, 'client_id' => $client->id, 'type' => 'call', 'occurred_at' => now()->subDays(2)]);
        Followup::create(['owner_id' => $rep->id, 'client_id' => $client->id, 'title' => 'HR fup', 'due_at' => now()->addDay(), 'status' => 'done']);

        $svc = app(PerformanceServiceInterface::class);
        $m = now()->format('Y-m');
        $p = $svc->score($rep->id, $m);

        $this->assertSame(25000000, $p['crm']['won_value_centavos']);
        $this->assertSame(1, $p['crm']['touches']);
        $this->assertSame(1, $p['crm']['followups_done']);
        $this->assertNotNull($p['composite']);
        $this->assertTrue($p['mock']);
        $this->assertArrayHasKey('rating', $p['hr']);

        // Deterministic + invalid period guard.
        $this->assertSame($p, $svc->score($rep->id, $m));
        $this->assertNull($svc->score($rep->id, 'soon')['composite']);
    }
}
