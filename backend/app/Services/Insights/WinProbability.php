<?php

namespace App\Services\Insights;

use App\Models\Opportunity;

/** v1 win probability: stage default × recency/activity multiplier (specs/15). Deterministic. */
class WinProbability
{
    public static function forOpp(Opportunity $opp): array
    {
        $base = Opportunity::STAGE_PROBABILITY[$opp->stage] ?? 10;
        $mult = 1.0;
        $drivers = ["Stage '{$opp->stage}' baseline {$base}%"];

        $daysStale = $opp->updated_at ? $opp->updated_at->diffInDays(now()) : 0;
        if ($daysStale > 60) {
            $mult *= 0.7;
            $drivers[] = "No movement in 60+ days ({$daysStale}d)";
        } elseif ($daysStale > 30) {
            $mult *= 0.85;
            $drivers[] = "No movement in 30+ days ({$daysStale}d)";
        }

        $recentActivity = \App\Models\Activity::where('client_id', $opp->client_id)
            ->where('occurred_at', '>=', now()->subDays(14))->exists();
        if ($recentActivity) {
            $mult *= 1.1;
            $drivers[] = 'Active engagement in the last 14 days';
        }

        if ($opp->expected_close_date && $opp->expected_close_date->isPast() && ! in_array($opp->stage, ['won', 'lost'], true)) {
            $mult *= 0.6;
            $drivers[] = 'Past expected close date';
        }

        $prob = (int) max(0, min(100, round($base * $mult)));

        return ['probability' => $prob, 'drivers' => $drivers, 'confidence' => 55, 'rules_based' => true];
    }
}
