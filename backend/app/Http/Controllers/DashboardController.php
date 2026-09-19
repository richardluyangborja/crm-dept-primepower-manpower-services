<?php

namespace App\Http\Controllers;

use App\Services\Insights\ForecastService;
use App\Services\Insights\NextBestAction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Dashboard aggregates (specs/05 + 15). Agent G extends — same envelope. */
class DashboardController extends Controller
{
    use ApiResponse;

    public function summary(Request $request)
    {
        $user = auth('api')->user();
        $forecast = ForecastService::pipelineTotals($user);
        $byStage = \App\Models\Opportunity::visibleTo($user)
            ->selectRaw('stage, COUNT(*) as count, COALESCE(SUM(value_centavos),0) as value')
            ->groupBy('stage')->get();
        $nps = \App\Models\SurveyResponse::query()
            ->whereHas('survey', fn ($q) => $q->where('template_id', function ($qq) {
                $qq->select('id')->from('survey_templates')->where('type', 'nps')->limit(1);
            }))->pluck('score');

        return $this->ok([
            'forecast' => $forecast,
            'by_stage' => $byStage,
            'nps_avg' => $nps->isNotEmpty() ? round($nps->avg(), 2) : null,
            'nps_count' => $nps->count(),
            'next_best_actions' => NextBestAction::forUser($user),
            'meta' => ['ai_fallback' => true, 'note' => 'Rules-based v1 — Agent G stream adds models'],
        ]);
    }
}
