<?php

namespace App\Http\Controllers;

use App\Services\Insights\ForecastService;
use App\Services\Insights\NextBestAction;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Dashboard aggregates (specs/05 + 06 + 15): forecast, at-risk, leaderboard, NBA. */
class DashboardController extends Controller
{
    use ApiResponse;

    public function summary(Request $request, ReportService $reports)
    {
        $user = auth('api')->user();
        $forecast = ForecastService::pipelineTotals($user);
        $forecast['ai_adjusted_centavos'] = $reports->aiAdjustedForecast($user)['ai_adjusted_centavos'];
        $byStage = \App\Models\Opportunity::visibleTo($user)
            ->selectRaw('stage, COUNT(*) as count, COALESCE(SUM(value_centavos),0) as value')
            ->groupBy('stage')->get();

        // Slow-moving aggregates: 60s per-user cache. Everything else below
        // stays live (single cheap queries each).
        $cached = \Illuminate\Support\Facades\Cache::remember(
            "dash:{$user->id}:v1",
            60,
            fn () => [
                'nps' => $this->npsAggregate($user),
                'trend' => $this->sixMonthTrend($user),
                'leaderboard' => $this->leaderboard($user),
            ]
        );

        // At-risk clients, ranked (specs/15) — bulk inputs, no per-client loop.
        $atRisk = $this->atRisk($user);

        // Trailing-90d win rate + avg sales cycle (bounded window, small).
        [$winRate90, $avgCycle] = $this->recentCloses($user);

        return $this->ok([
            'forecast' => $forecast,
            'trends' => ['monthly' => $cached['trend']],
            'by_stage' => $byStage,
            'nps_avg' => $cached['nps']['avg'],
            'nps_count' => $cached['nps']['count'],
            'win_rate_90d' => $winRate90,
            'avg_cycle_days' => $avgCycle,
            'at_risk' => $atRisk,
            'leaderboard' => $cached['leaderboard'],
            'next_best_actions' => NextBestAction::forUser($user),
            'meta' => ['ai_fallback' => true, 'ai_preview' => true, 'note' => 'Rules-based v1 (mock-AI labeled)'],
        ]);
    }

    /** All-time NPS in one aggregate row instead of an unbounded pluck. */
    protected function npsAggregate(mixed $user): array
    {
        $row = \App\Models\SurveyResponse::query()
            ->whereHas('survey', function ($q) use ($user) {
                $q->visibleTo($user);
                $q->where('template_id', function ($qq) {
                    $qq->select('id')->from('survey_templates')->where('type', 'nps')->limit(1);
                });
            })
            ->selectRaw('COALESCE(AVG(score), 0) as avg, COUNT(*) as count')
            ->first();

        return ['avg' => $row && $row->count ? round((float) $row->avg, 2) : null, 'count' => (int) ($row->count ?? 0)];
    }

    /**
     * Trailing-6-month trends in 4 bounded queries (6-month window keeps
     * each small) with PHP bucketing — replaces 24 round trips.
     */
    protected function sixMonthTrend(mixed $user): array
    {
        $since = now()->subMonths(6)->startOfMonth();
        $wonDates = \App\Models\Opportunity::visibleTo($user)->where('stage', 'won')
            ->where('won_at', '>=', $since)->pluck('won_at');
        $lostDates = \App\Models\Opportunity::visibleTo($user)->where('stage', 'lost')
            ->where('lost_at', '>=', $since)->pluck('lost_at');
        $newDates = \App\Models\Opportunity::visibleTo($user)
            ->where('created_at', '>=', $since)->pluck('created_at');
        $npsRows = \App\Models\SurveyResponse::query()
            ->whereHas('survey', fn ($q) => $q->visibleTo($user))
            ->where('responded_at', '>=', $since)->get(['score', 'responded_at']);

        $bucket = function ($dates, string $month) {
            return $dates->filter(fn ($d) => $d && \Carbon\Carbon::parse($d)->format('Y-m') === $month)->count();
        };
        $trend = [];
        foreach (range(5, 0) as $i) {
            $month = now()->subMonths($i)->format('Y-m');
            $won = $bucket($wonDates, $month);
            $lost = $bucket($lostDates, $month);
            $monthScores = $npsRows->filter(fn ($r) => $r->responded_at && $r->responded_at->format('Y-m') === $month)->pluck('score');
            $closedMo = $won + $lost;
            $trend[] = [
                'month' => $month,
                'won' => $won,
                'lost' => $lost,
                'win_rate' => $closedMo ? round($won / $closedMo * 100, 1) : null,
                'new_opps' => $bucket($newDates, $month),
                'nps_avg' => $monthScores->isNotEmpty() ? round($monthScores->avg(), 2) : null,
            ];
        }

        return $trend;
    }

    /** Leaderboard: one grouped query + eager owner (was N+1 User::find). */
    protected function leaderboard(mixed $user): array
    {
        return \App\Models\Opportunity::visibleTo($user)->where('stage', 'won')
            ->with('owner:id,name')
            ->selectRaw('owner_id, COUNT(*) as deals, COALESCE(SUM(value_centavos),0) as value')
            ->groupBy('owner_id')->orderByDesc('value')->limit(5)->get()
            ->map(fn ($r) => [
                'owner_id' => $r->owner_id,
                'owner_name' => $r->owner?->name,
                'deals' => (int) $r->deals,
                'value_centavos' => (int) $r->value,
            ])->values()->all();
    }

    /**
     * Bulk churn inputs: one overdue-counts-per-client query + one
     * low-survey client list + one slim client fetch. Same scoring math
     * as ChurnRisk::assess, zero per-client queries.
     */
    protected function atRisk(mixed $user): array
    {
        $clients = \App\Models\Client::visibleTo($user)
            ->with('owner:id,name')
            ->get(['id', 'name', 'owner_id', 'status', 'last_contacted_at']);
        if ($clients->isEmpty()) {
            return [];
        }
        $ids = $clients->pluck('id');
        $overdueByClient = \App\Models\Followup::whereIn('client_id', $ids)
            ->whereIn('status', ['overdue', 'escalated'])
            ->selectRaw('client_id, COUNT(*) as count')->groupBy('client_id')
            ->pluck('count', 'client_id');
        $lowClients = \App\Models\Survey::whereIn('client_id', $ids)
            ->whereHas('responses', fn ($q) => $q->where('score', '<=', 6))
            ->distinct()->pluck('client_id')->flip();

        $atRisk = [];
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
            if (isset($lowClients[$client->id])) {
                $points += 2;
                $drivers[] = 'Low satisfaction score (≤6)';
            }
            if ($client->status === 'inactive') {
                $points += 2;
                $drivers[] = 'Client marked inactive';
            }
            $level = $points >= 4 ? 'high' : ($points >= 2 ? 'medium' : 'low');
            if ($level !== 'low') {
                $atRisk[] = [
                    'client_id' => \App\Models\Client::encodeId($client->id),
                    'client_name' => $client->name,
                    'owner_name' => $client->owner?->name,
                    'level' => $level,
                    'drivers' => $drivers,
                ];
            }
        }
        usort($atRisk, fn ($a, $b) => ($b['level'] === 'high') <=> ($a['level'] === 'high'));

        return array_slice($atRisk, 0, 5);
    }

    /** Trailing-90d closes: counts in SQL, cycle math on slim date pairs. */
    protected function recentCloses(mixed $user): array
    {
        $base = \App\Models\Opportunity::visibleTo($user)->whereIn('stage', ['won', 'lost'])
            ->where(function ($q) {
                $q->where('won_at', '>=', now()->subDays(90))->orWhere('lost_at', '>=', now()->subDays(90));
            });
        $closed = (clone $base)->count();
        $wonCount = (clone $base)->where('stage', 'won')->count();
        $cycles = (clone $base)->where('stage', 'won')->whereNotNull('won_at')
            ->get(['created_at', 'won_at'])
            ->map(fn ($o) => $o->created_at->diffInDays($o->won_at));

        return [
            $closed ? round($wonCount / $closed * 100, 1) : null,
            $cycles->isNotEmpty() ? round($cycles->avg(), 1) : null,
        ];
    }

}
