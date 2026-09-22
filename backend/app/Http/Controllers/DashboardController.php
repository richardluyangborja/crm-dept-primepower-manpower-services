<?php

namespace App\Http\Controllers;

use App\Services\Insights\ChurnRisk;
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
        $nps = \App\Models\SurveyResponse::query()
            ->whereHas('survey', fn ($q) => $q->where('template_id', function ($qq) {
                $qq->select('id')->from('survey_templates')->where('type', 'nps')->limit(1);
            }))->pluck('score');

        // At-risk clients, ranked (specs/15).
        $atRisk = [];
        foreach (\App\Models\Client::visibleTo($user)->with('owner:id,name')->get() as $client) {
            $risk = ChurnRisk::assess($client);
            if ($risk['level'] !== 'low') {
                $atRisk[] = [
                    'client_id' => \App\Models\Client::encodeId($client->id),
                    'client_name' => $client->name,
                    'owner_name' => $client->owner?->name,
                    'level' => $risk['level'],
                    'drivers' => $risk['drivers'],
                ];
            }
        }
        usort($atRisk, fn ($a, $b) => ($b['level'] === 'high') <=> ($a['level'] === 'high'));
        $atRisk = array_slice($atRisk, 0, 5);

        // Trailing-90d win rate + avg sales cycle + rep leaderboard.
        $recent = \App\Models\Opportunity::visibleTo($user)->whereIn('stage', ['won', 'lost'])
            ->where(function ($q) {
                $q->where('won_at', '>=', now()->subDays(90))->orWhere('lost_at', '>=', now()->subDays(90));
            })->get();
        $closed = $recent->count();
        $wonCount = $recent->where('stage', 'won')->count();
        $cycles = $recent->where('stage', 'won')->filter(fn ($o) => $o->won_at)
            ->map(fn ($o) => $o->created_at->diffInDays($o->won_at));
        $leaderboard = \App\Models\Opportunity::visibleTo($user)->where('stage', 'won')
            ->selectRaw('owner_id, COUNT(*) as deals, COALESCE(SUM(value_centavos),0) as value')
            ->groupBy('owner_id')->orderByDesc('value')->limit(5)->get()
            ->map(fn ($r) => [
                'owner_id' => $r->owner_id,
                'owner_name' => \App\Models\User::find($r->owner_id)?->name,
                'deals' => (int) $r->deals,
                'value_centavos' => (int) $r->value,
            ])->values()->all();

        // Trailing-6-month trends (specs/15 BI expansion): closes, win rate,
        // new pipeline, and NPS — all in the viewer's scope.
        $trend = [];
        foreach (range(5, 0) as $i) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $won = \App\Models\Opportunity::visibleTo($user)->where('stage', 'won')
                ->whereBetween('won_at', [$start, $end])->count();
            $lost = \App\Models\Opportunity::visibleTo($user)->where('stage', 'lost')
                ->whereBetween('lost_at', [$start, $end])->count();
            $newOpps = \App\Models\Opportunity::visibleTo($user)
                ->whereBetween('created_at', [$start, $end])->count();
            $monthNps = \App\Models\SurveyResponse::query()
                ->whereHas('survey', fn ($q) => $q->visibleTo($user))
                ->whereBetween('responded_at', [$start, $end])->pluck('score');
            $closedMo = $won + $lost;
            $trend[] = [
                'month' => $start->format('Y-m'),
                'won' => $won,
                'lost' => $lost,
                'win_rate' => $closedMo ? round($won / $closedMo * 100, 1) : null,
                'new_opps' => $newOpps,
                'nps_avg' => $monthNps->isNotEmpty() ? round($monthNps->avg(), 2) : null,
            ];
        }

        return $this->ok([
            'forecast' => $forecast,
            'trends' => ['monthly' => $trend],
            'by_stage' => $byStage,
            'nps_avg' => $nps->isNotEmpty() ? round($nps->avg(), 2) : null,
            'nps_count' => $nps->count(),
            'win_rate_90d' => $closed ? round($wonCount / $closed * 100, 1) : null,
            'avg_cycle_days' => $cycles->isNotEmpty() ? round($cycles->avg(), 1) : null,
            'at_risk' => $atRisk,
            'leaderboard' => $leaderboard,
            'next_best_actions' => NextBestAction::forUser($user),
            'meta' => ['ai_fallback' => true, 'ai_preview' => true, 'note' => 'Rules-based v1 (mock-AI labeled)'],
        ]);
    }
}
