<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\DB;

/** Analytics for NPS/CSAT dashboards (specs/06 + 15 hooks). */
class SurveyAnalyticsService
{
    public function forScope(?int $teamId, ?string $from, ?string $to): array
    {
        $q = Survey::query()->with(['client.owner', 'template']);
        if ($teamId) $q->whereHas('client.owner', fn ($qq) => $qq->where('team_id', $teamId));
        if ($from) $q->where('created_at', '>=', $from);
        if ($to) $q->where('created_at', '<=', $to);
        $surveys = $q->get();
        $responses = SurveyResponse::whereIn('survey_id', $surveys->pluck('id'))->get();

        $npsScores = $responses->filter(fn ($r) => optional($r->survey?->template)->type === 'nps' || $r->score >= 0)->pluck('score'); // fallback to all if type not filtered
        // More precise: separate by template type when available
        $nps = $responses->filter(fn ($r) => $r->survey?->template?->type === 'nps')->pluck('score');
        if ($nps->isEmpty()) $nps = $npsScores->filter(fn ($s) => $s >= 0 && $s <= 10);
        $csat = $responses->filter(fn ($r) => $r->survey?->template?->type === 'csat')->pluck('score');

        $promoters = $nps->filter(fn ($s) => $s >= 9)->count();
        $passives = $nps->filter(fn ($s) => $s >= 7 && $s <= 8)->count();
        $detractors = $nps->filter(fn ($s) => $s <= 6)->count();
        $totalNps = $promoters + $passives + $detractors;
        $npsScore = $totalNps ? (int) round((($promoters - $detractors) / $totalNps) * 100) : null;

        $total = $surveys->count();
        $responded = $surveys->where('status', 'responded')->count();
        $responseRate = $total ? round(($responded / $total) * 100, 1) : null;

        // Trend: last 6 months bucketed
        $trend = [];
        foreach (range(5, 0) as $i) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $mo = $responses->filter(fn ($r) => $r->responded_at && $r->responded_at->between($start, $end));
            $trend[] = [
                'month' => $start->format('Y-m'),
                'avg' => $mo->isNotEmpty() ? round($mo->avg('score'), 2) : null,
                'count' => $mo->count(),
            ];
        }

        // Per-client + low-score flag
        $perClient = $surveys->groupBy('client_id')->map(function ($group) {
            $scores = SurveyResponse::whereIn('survey_id', $group->pluck('id'))->pluck('score');
            return [
                'client_id' => $group->first()->client_id,
                'client_name' => $group->first()->client?->name,
                'surveys' => $group->count(),
                'avg_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'low' => $scores->contains(fn ($s) => $s !== null && $s < 7),
            ];
        })->values()->all();

        $lowScores = $responses->filter(fn ($r) => $r->score !== null && $r->score < 7)
            ->map(fn ($r) => [
                'survey_id' => $r->survey_id,
                'client_name' => $r->survey?->client?->name,
                'score' => $r->score,
                'comment' => $r->comment,
                'responded_at' => $r->responded_at,
            ])->values()->all();

        return [
            'nps' => ['score' => $npsScore, 'promoters' => $promoters, 'passives' => $passives, 'detractors' => $detractors, 'total' => $totalNps, 'avg' => $nps->isNotEmpty() ? round($nps->avg(), 2) : null],
            'csat_avg' => $csat->isNotEmpty() ? round($csat->avg(), 2) : null,
            'response_rate' => $responseRate,
            'totals' => ['surveys' => $total, 'responded' => $responded, 'pending' => $surveys->where('status', 'sent')->count(), 'expired' => $surveys->where('status', 'expired')->count()],
            'trend' => $trend,
            'per_client' => $perClient,
            'low_scores' => $lowScores,
            'comments' => $responses->filter(fn ($r) => $r->comment)->map(fn ($r) => ['score' => $r->score, 'comment' => $r->comment, 'client_name' => $r->survey?->client?->name, 'at' => $r->responded_at])->values()->all(),
        ];
    }
}
