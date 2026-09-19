<?php

namespace App\Services\Insights;

use App\Models\Client;

/** v1 churn/at-risk flags (specs/15). Returns level + human-readable drivers. */
class ChurnRisk
{
    public static function assess(Client $client): array
    {
        $drivers = [];
        $points = 0;

        $lastActivity = $client->last_contacted_at;
        if (! $lastActivity || $lastActivity->lt(now()->subDays(30))) {
            $points += 2;
            $drivers[] = 'No contact in 30+ days';
        }
        $overdue = \App\Models\Followup::where('client_id', $client->id)
            ->whereIn('status', ['overdue', 'escalated'])->count();
        if ($overdue >= 2) {
            $points += 2;
            $drivers[] = "{$overdue} overdue follow-ups";
        } elseif ($overdue === 1) {
            $points += 1;
            $drivers[] = '1 overdue follow-up';
        }
        $lowSurvey = \App\Models\Survey::where('client_id', $client->id)
            ->whereHas('responses', fn ($q) => $q->where('score', '<=', 6))->exists();
        if ($lowSurvey) {
            $points += 2;
            $drivers[] = 'Low satisfaction score (≤6)';
        }
        if ($client->status === 'inactive') {
            $points += 2;
            $drivers[] = 'Client marked inactive';
        }

        $level = $points >= 4 ? 'high' : ($points >= 2 ? 'medium' : 'low');
        if ($drivers === []) {
            $drivers[] = 'Healthy engagement';
        }

        return ['level' => $level, 'drivers' => $drivers, 'confidence' => 60, 'rules_based' => true];
    }
}
