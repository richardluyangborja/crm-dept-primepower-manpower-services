<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Followup;
use App\Models\Opportunity;
use App\Models\SurveyResponse;
use App\Services\Insights\ForecastService;
use App\Services\Insights\NextBestAction;
use App\Services\Insights\SentimentAnalyzer;

/** Management report packs (specs/15): narrative + tables, rules-based v1, mock-AI labeled. */
class ReportService
{
    public function __construct(protected SurveyAnalyticsService $surveys) {}

    /**
     * @return array{narrative: string, tables: array, meta: array}
     */
    public function pack(mixed $user, string $type, string $from, string $to, ?int $teamId = null): array
    {
        // Packs are slow-moving aggregates: 60s cache per viewer+period.
        $key = 'pack:'.($user->id ?? 'guest').":{$user->role}:".($user->team_id ?? 0).":{$type}:{$from}:{$to}:".($teamId ?? 0);

        return \Illuminate\Support\Facades\Cache::remember($key, 60, fn () => $this->buildPack($user, $type, $from, $to, $teamId));
    }

    protected function buildPack(mixed $user, string $type, string $from, string $to, ?int $teamId = null): array
    {
        $totals = ForecastService::pipelineTotals($user);

        $byStage = Opportunity::visibleTo($user)
            ->selectRaw('stage, COUNT(*) as count, COALESCE(SUM(value_centavos),0) as value')
            ->groupBy('stage')->get()
            ->map(fn ($r) => ['stage' => $r->stage, 'count' => (int) $r->count, 'value_centavos' => (int) $r->value])
            ->values()->all();

        $wonCount = Opportunity::visibleTo($user)->where('stage', 'won')->count();
        $lostCount = Opportunity::visibleTo($user)->where('stage', 'lost')->count();
        $closed = $wonCount + $lostCount;
        $winRate = $closed ? round($wonCount / $closed * 100, 1) : null;

        $sat = $this->surveys->forScope($teamId, $from, $to);

        $actBase = Activity::query()
            ->when(! in_array($user->role, ['superadmin', 'admin'], true), function ($q) use ($user) {
                if ($user->role === 'manager' && $user->team_id) {
                    $q->whereIn('owner_id', fn ($qq) => $qq->select('id')->from('users')->where('team_id', $user->team_id));
                } else {
                    $q->where('owner_id', $user->id);
                }
            })
            ->whereBetween('occurred_at', [$from, $to]);
        $activityByType = (clone $actBase)->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type')->all();
        $activityByOwner = (clone $actBase)->selectRaw('owner_id, COUNT(*) as count')->groupBy('owner_id')->pluck('count', 'owner_id');
        $ownerNames = $activityByOwner->isEmpty() ? collect() : \App\Models\User::whereIn('id', $activityByOwner->keys())->pluck('name', 'id');
        $activityByOwner = $activityByOwner->map(fn ($c, $id) => [
            'owner_id' => (int) $id,
            'owner_name' => $ownerNames[$id] ?? null,
            'count' => (int) $c,
        ])->values()->all();
        $activityCount = array_sum($activityByType);

        $comp = Followup::visibleTo($user)
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'open') as open, COUNT(*) FILTER (WHERE status = 'done') as done, COUNT(*) FILTER (WHERE status IN ('overdue','escalated')) as overdue")
            ->first();
        // FILTER is Postgres-only; fall back for sqlite/mysql test runs.
        if ($comp && $comp->open === null && \Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
            $comp = Followup::visibleTo($user)
                ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done, SUM(CASE WHEN status IN ('overdue','escalated') THEN 1 ELSE 0 END) as overdue")
                ->first();
        }
        $compliance = ['open' => (int) ($comp->open ?? 0), 'done' => (int) ($comp->done ?? 0), 'overdue' => (int) ($comp->overdue ?? 0)];

        $risks = $this->bulkRisks($user);

        // Latest 50 commented responses — was an unbounded scan + PHP sentiment.
        $comments = SurveyResponse::query()
            ->whereHas('survey', function ($q) use ($from, $to, $teamId) {
                $q->whereBetween('responded_at', [$from, $to]);
                if ($teamId) $q->whereHas('client.owner', fn ($qq) => $qq->where('team_id', $teamId));
            })
            ->whereNotNull('comment')->with('survey.client:id,name')->latest('responded_at')->limit(50)->get()
            ->map(fn ($r) => [
                'client_name' => $r->survey?->client?->name,
                'score' => $r->score,
                'comment' => $r->comment,
                'sentiment' => SentimentAnalyzer::analyze($r->comment),
            ])->values()->all();

        $topRisk = $risks[0] ?? null;
        $narrative = $this->narrate($type, $from, $to, [
            'open' => $totals['open_centavos'], 'weighted' => $totals['weighted_centavos'], 'openCount' => $totals['count'],
            'winRate' => $winRate, 'nps' => $sat['nps']['score'] ?? null,
            'responseRate' => $sat['response_rate'] ?? null,
            'activityCount' => $activityCount, 'overdue' => $compliance['overdue'],
            'riskCount' => count($risks), 'topRisk' => $topRisk,
        ]);

        return [
            'narrative' => $narrative,
            'tables' => [
                'pipeline_by_stage' => $byStage,
                'forecast' => ['open_centavos' => $totals['open_centavos'], 'weighted_centavos' => $totals['weighted_centavos'], 'open_count' => $totals['count'], 'win_rate' => $winRate],
                'satisfaction' => ['nps' => $sat['nps'], 'csat_avg' => $sat['csat_avg'], 'response_rate' => $sat['response_rate'], 'totals' => $sat['totals']],
                'activity_by_type' => $activityByType,
                'activity_by_owner' => $activityByOwner,
                'followup_compliance' => $compliance,
                'risks' => $risks,
                'comment_sentiment' => $comments,
                'deal_trend_12m' => $this->dealTrend($user),
                'satisfaction_trend_12m' => $this->satisfactionTrend($user),
                'satisfaction_by_client' => $this->satisfactionByClient($user, $from, $to),
            ],
            'meta' => ['ai_preview' => true, 'rules_based' => true, 'generated_at' => now()->toIso8601String(), 'period' => ['from' => $from, 'to' => $to], 'team_id' => $teamId],
        ];
    }

    /**
     * Bulk churn + bulk NBA: ~10 queries total no matter the client count.
     * Same scoring math as ChurnRisk::assess.
     */
    protected function bulkRisks(mixed $user): array
    {
        $clients = Client::visibleTo($user)->with('owner:id,name')
            ->get(['id', 'name', 'owner_id', 'status', 'last_contacted_at']);
        if ($clients->isEmpty()) {
            return [];
        }
        $ids = $clients->pluck('id');
        $overdueByClient = \App\Models\Followup::whereIn('client_id', $ids)
            ->whereIn('status', ['overdue', 'escalated'])
            ->selectRaw('client_id, COUNT(*) as count')->groupBy('client_id')->pluck('count', 'client_id');
        $lowSet = \App\Models\Survey::whereIn('client_id', $ids)
            ->whereHas('responses', fn ($q) => $q->where('score', '<=', 6))
            ->distinct()->pluck('client_id')->flip();

        $rows = [];
        foreach ($clients as $c) {
            $rows[$c->id] = ['id' => $c->id, 'name' => $c->name, 'last_contacted_at' => $c->last_contacted_at];
        }
        $nba = NextBestAction::forClients($rows, 2);

        $risks = [];
        foreach ($clients as $client) {
            $drivers = [];
            $points = 0;
            if (! $client->last_contacted_at || $client->last_contacted_at->lt(now()->subDays(30))) {
                $points += 2;
                $drivers[] = 'No contact in 30+ days';
            }
            $overdue = (int) ($overdueByClient[$client->id] ?? 0);
            if ($overdue >= 2) {
                $points += 2;
                $drivers[] = "{$overdue} overdue follow-ups";
            } elseif ($overdue === 1) {
                $points += 1;
                $drivers[] = '1 overdue follow-up';
            }
            if (isset($lowSet[$client->id])) {
                $points += 2;
                $drivers[] = 'Low satisfaction score (≤6)';
            }
            if ($client->status === 'inactive') {
                $points += 2;
                $drivers[] = 'Client marked inactive';
            }
            $level = $points >= 4 ? 'high' : ($points >= 2 ? 'medium' : 'low');
            if ($level !== 'low') {
                $risks[] = [
                    'client_id' => \App\Models\Client::encodeId($client->id),
                    'client_name' => $client->name,
                    'owner_name' => $client->owner?->name,
                    'level' => $level,
                    'drivers' => $drivers,
                    'nba' => $nba[$client->id] ?? [],
                ];
            }
        }
        usort($risks, fn ($a, $b) => ($b['level'] === 'high') <=> ($a['level'] === 'high'));

        return $risks;
    }

    /**
     * Trailing 12 months of closes + win rate + new pipeline, in viewer scope.
     * Feeds the management-report sales performance chart.
     *
     * @return list<array{month: string, won: int, lost: int, win_rate: float|null, new_opps: int}>
     */
    protected function dealTrend(mixed $user): array
    {
        // 3 bounded queries (12-month window, indexed) + PHP bucketing.
        $since = now()->subMonths(12)->startOfMonth();
        $wonDates = Opportunity::visibleTo($user)->where('stage', 'won')
            ->where('won_at', '>=', $since)->pluck('won_at');
        $lostDates = Opportunity::visibleTo($user)->where('stage', 'lost')
            ->where('lost_at', '>=', $since)->pluck('lost_at');
        $newDates = Opportunity::visibleTo($user)
            ->where('created_at', '>=', $since)->pluck('created_at');
        $countMonth = fn ($dates, $month) => $dates->filter(fn ($d) => $d && \Carbon\Carbon::parse($d)->format('Y-m') === $month)->count();

        $trend = [];
        foreach (range(11, 0) as $i) {
            $month = now()->subMonths($i)->format('Y-m');
            $won = $countMonth($wonDates, $month);
            $lost = $countMonth($lostDates, $month);
            $closed = $won + $lost;
            $trend[] = [
                'month' => $month,
                'won' => $won,
                'lost' => $lost,
                'win_rate' => $closed ? round($won / $closed * 100, 1) : null,
                'new_opps' => $countMonth($newDates, $month),
            ];
        }

        return $trend;
    }

    /**
     * Trailing 12 months of satisfaction: response average + true NPS,
     * in viewer scope. Feeds the management-report satisfaction chart.
     *
     * @return list<array{month: string, avg: float|null, nps: int|null, responses: int}>
     */
    protected function satisfactionTrend(mixed $user): array
    {
        // 1 bounded query (12-month window, indexed) + PHP bucketing.
        $since = now()->subMonths(12)->startOfMonth();
        $rows = SurveyResponse::query()
            ->whereHas('survey', fn ($q) => $q->visibleTo($user))
            ->where('responded_at', '>=', $since)->get(['score', 'responded_at']);
        $byMonth = $rows->groupBy(fn ($r) => $r->responded_at ? $r->responded_at->format('Y-m') : 'none');

        $trend = [];
        foreach (range(11, 0) as $i) {
            $month = now()->subMonths($i)->format('Y-m');
            $scores = ($byMonth[$month] ?? collect())->pluck('score');
            $npsScores = $scores->filter(fn ($s) => $s !== null && $s >= 0 && $s <= 10);
            $promoters = $npsScores->filter(fn ($s) => $s >= 9)->count();
            $detractors = $npsScores->filter(fn ($s) => $s <= 6)->count();
            $trend[] = [
                'month' => $month,
                'avg' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'nps' => $npsScores->isNotEmpty() ? (int) round(($promoters - $detractors) / $npsScores->count() * 100) : null,
                'responses' => $scores->count(),
            ];
        }

        return $trend;
    }

    /**
     * Per-client satisfaction for the period, best first.
     *
     * @return list<array{client_name: string|null, surveys: int, responded: int, avg_score: float|null, low: bool}>
     */
    protected function satisfactionByClient(mixed $user, string $from, string $to): array
    {
        // 2 queries: period surveys, then ALL their scores at once (was N+1 per client).
        $surveys = \App\Models\Survey::query()->visibleTo($user)
            ->whereBetween('created_at', [$from, $to])->with('client:id,name')->get();
        $scoresBySurvey = $surveys->isEmpty() ? collect() : SurveyResponse::whereIn('survey_id', $surveys->pluck('id'))
            ->get(['survey_id', 'score'])->groupBy('survey_id');
        $rows = $surveys->groupBy('client_id')->map(function ($group) use ($scoresBySurvey) {
            $scores = $group->flatMap(fn ($s) => ($scoresBySurvey[$s->id] ?? collect())->pluck('score'));
            return [
                'client_name' => $group->first()->client?->name,
                'surveys' => $group->count(),
                'responded' => $group->where('status', 'responded')->count(),
                'avg_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'low' => $scores->contains(fn ($s) => $s !== null && $s < 7),
            ];
        })->values()->sortByDesc('responded')->values()->all();

        return $rows;
    }

    protected function narrate(string $type, string $from, string $to, array $n): string
    {
        $fmt = fn ($c) => '₱'.number_format($c / 100, 0);
        $lines = [];
        $lines[] = ucfirst($type).' performance ('.$from.' to '.$to.'): '.$n['openCount'].' open opportunities worth '.$fmt($n['open']).', weighted forecast '.$fmt($n['weighted']).'.';
        $lines[] = $n['winRate'] === null ? 'No closed deals in scope yet — move opportunities to won/lost to unlock win-rate.' : 'Win rate '.$n['winRate'].'% on closed deals.';
        $lines[] = $n['nps'] === null ? 'No NPS responses in scope yet — send surveys to unlock satisfaction.' : 'NPS '.$n['nps'].' with '.$n['responseRate'].'% response rate.';
        $lines[] = $n['activityCount'].' logged touchpoints; '.$n['overdue'].' follow-ups need attention.';
        $lines[] = $n['riskCount'] === 0
            ? 'No at-risk clients — engagement looks healthy.'
            : $n['riskCount'].' at-risk client(s). Top concern: '.$n['topRisk']['client_name'].' ('.$n['topRisk']['level'].' — '.implode('; ', $n['topRisk']['drivers']).').';
        $lines[] = 'Generated by rules engine v1 (mock-AI labeled); verify before board use.';

        return implode(' ', $lines);
    }

    /** AI-adjusted forecast: stage baseline × recency/activity multiplier per opp. */
    public function aiAdjustedForecast(mixed $user): array
    {
        // Slim columns + one recent-activity client set — was O(opps) exists() calls.
        $opps = Opportunity::visibleTo($user)->whereNotIn('stage', ['won', 'lost'])
            ->get(['id', 'stage', 'value_centavos', 'updated_at', 'expected_close_date', 'client_id']);
        $activeClients = $opps->pluck('client_id')->filter()->unique();
        $recent = $activeClients->isEmpty() ? collect() : \App\Models\Activity::whereIn('client_id', $activeClients)
            ->where('occurred_at', '>=', now()->subDays(14))->distinct()->pluck('client_id')->flip();

        $adjusted = 0;
        foreach ($opps as $o) {
            $base = Opportunity::STAGE_PROBABILITY[$o->stage] ?? 10;
            $mult = 1.0;
            $daysStale = $o->updated_at ? $o->updated_at->diffInDays(now()) : 0;
            if ($daysStale > 60) {
                $mult *= 0.7;
            } elseif ($daysStale > 30) {
                $mult *= 0.85;
            }
            if (isset($recent[$o->client_id])) {
                $mult *= 1.1;
            }
            if ($o->expected_close_date && $o->expected_close_date->isPast() && ! in_array($o->stage, ['won', 'lost'], true)) {
                $mult *= 0.6;
            }
            $adjusted += (int) round($o->value_centavos * (max(0, min(100, round($base * $mult))) / 100));
        }

        return ['ai_adjusted_centavos' => $adjusted, 'opp_count' => $opps->count()];
    }
}
