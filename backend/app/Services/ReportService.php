<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Followup;
use App\Models\Opportunity;
use App\Models\SurveyResponse;
use App\Services\Insights\ChurnRisk;
use App\Services\Insights\ForecastService;
use App\Services\Insights\NextBestAction;
use App\Services\Insights\SentimentAnalyzer;
use App\Services\Insights\WinProbability;

/** Management report packs (specs/15): narrative + tables, rules-based v1, mock-AI labeled. */
class ReportService
{
    public function __construct(protected SurveyAnalyticsService $surveys) {}

    /**
     * @return array{narrative: string, tables: array, meta: array}
     */
    public function pack(mixed $user, string $type, string $from, string $to, ?int $teamId = null): array
    {
        $opps = Opportunity::visibleTo($user)->with('client:id,name')->get();
        $openOpps = $opps->whereNotIn('stage', ['won', 'lost']);
        $open = $openOpps->sum('value_centavos');
        $weighted = $openOpps->sum(fn ($o) => ForecastService::weighted($o->value_centavos, $o->probability));

        $byStage = $opps->groupBy('stage')->map(fn ($g, $stage) => [
            'stage' => $stage,
            'count' => $g->count(),
            'value_centavos' => $g->sum('value_centavos'),
        ])->values()->all();

        $won = $opps->where('stage', 'won');
        $lost = $opps->where('stage', 'lost');
        $closed = $won->count() + $lost->count();
        $winRate = $closed ? round($won->count() / $closed * 100, 1) : null;

        $sat = $this->surveys->forScope($teamId, $from, $to);

        $acts = Activity::query()
            ->when(! in_array($user->role, ['superadmin', 'admin'], true), function ($q) use ($user) {
                if ($user->role === 'manager' && $user->team_id) {
                    $q->whereIn('owner_id', fn ($qq) => $qq->select('id')->from('users')->where('team_id', $user->team_id));
                } else {
                    $q->where('owner_id', $user->id);
                }
            })
            ->whereBetween('occurred_at', [$from, $to])->get();
        $activityByType = $acts->groupBy('type')->map->count()->all();
        $activityByOwner = $acts->groupBy('owner_id')->map(fn ($g) => [
            'owner_id' => $g->first()->owner_id,
            'owner_name' => $g->first()->owner?->name,
            'count' => $g->count(),
        ])->values()->all();

        $fups = Followup::visibleTo($user)->get();
        $compliance = [
            'open' => $fups->where('status', 'open')->count(),
            'done' => $fups->where('status', 'done')->count(),
            'overdue' => $fups->whereIn('status', ['overdue', 'escalated'])->count(),
        ];

        $clients = Client::visibleTo($user)->with('owner:id,name')->get();
        $risks = [];
        foreach ($clients as $c) {
            $r = ChurnRisk::assess($c);
            if ($r['level'] !== 'low') {
                $risks[] = [
                    'client_id' => \App\Models\Client::encodeId($c->id),
                    'client_name' => $c->name,
                    'owner_name' => $c->owner?->name,
                    'level' => $r['level'],
                    'drivers' => $r['drivers'],
                    'nba' => NextBestAction::forClient($c, 2),
                ];
            }
        }
        usort($risks, fn ($a, $b) => ($b['level'] === 'high') <=> ($a['level'] === 'high'));

        $comments = SurveyResponse::query()
            ->whereHas('survey', function ($q) use ($from, $to, $teamId) {
                $q->whereBetween('responded_at', [$from, $to]);
                if ($teamId) $q->whereHas('client.owner', fn ($qq) => $qq->where('team_id', $teamId));
            })
            ->whereNotNull('comment')->with('survey.client')->get()
            ->map(fn ($r) => [
                'client_name' => $r->survey?->client?->name,
                'score' => $r->score,
                'comment' => $r->comment,
                'sentiment' => SentimentAnalyzer::analyze($r->comment),
            ])->values()->all();

        $topRisk = $risks[0] ?? null;
        $narrative = $this->narrate($type, $from, $to, [
            'open' => $open, 'weighted' => $weighted, 'openCount' => $openOpps->count(),
            'winRate' => $winRate, 'nps' => $sat['nps']['score'] ?? null,
            'responseRate' => $sat['response_rate'] ?? null,
            'activityCount' => $acts->count(), 'overdue' => $compliance['overdue'],
            'riskCount' => count($risks), 'topRisk' => $topRisk,
        ]);

        return [
            'narrative' => $narrative,
            'tables' => [
                'pipeline_by_stage' => $byStage,
                'forecast' => ['open_centavos' => $open, 'weighted_centavos' => $weighted, 'open_count' => $openOpps->count(), 'win_rate' => $winRate],
                'satisfaction' => ['nps' => $sat['nps'], 'csat_avg' => $sat['csat_avg'], 'response_rate' => $sat['response_rate'], 'totals' => $sat['totals']],
                'activity_by_type' => $activityByType,
                'activity_by_owner' => $activityByOwner,
                'followup_compliance' => $compliance,
                'risks' => $risks,
                'comment_sentiment' => $comments,
            ],
            'meta' => ['ai_preview' => true, 'rules_based' => true, 'generated_at' => now()->toIso8601String(), 'period' => ['from' => $from, 'to' => $to], 'team_id' => $teamId],
        ];
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
        $opps = Opportunity::visibleTo($user)->whereNotIn('stage', ['won', 'lost'])->get();
        $adjusted = $opps->sum(function ($o) {
            $p = WinProbability::forOpp($o)['probability'];

            return (int) round($o->value_centavos * ($p / 100));
        });

        return ['ai_adjusted_centavos' => $adjusted, 'opp_count' => $opps->count()];
    }
}
