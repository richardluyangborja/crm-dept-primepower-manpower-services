<?php

namespace App\Services\Insights;

/** v1 decision-tree next-best-actions (specs/15). Agent G extends. */
class NextBestAction
{
    public static function forUser(mixed $user, int $limit = 5): array
    {
        $actions = [];
        $overdue = \App\Models\Followup::visibleTo($user)->whereIn('status', ['overdue', 'escalated'])
            ->orderBy('due_at')->limit($limit)->get();
        foreach ($overdue as $f) {
            $actions[] = ['kind' => 'followup_overdue', 'title' => "Clear overdue: {$f->title}", 'link' => "/followups?status=overdue"];
            if (count($actions) >= $limit) {
                break;
            }
        }
        $stale = \App\Models\Client::visibleTo($user)->where(function ($q) {
            $q->whereNull('last_contacted_at')->orWhere('last_contacted_at', '<', now()->subDays(30));
        })->limit($limit - count($actions))->get();
        foreach ($stale as $c) {
            $actions[] = ['kind' => 'client_stale', 'title' => "Reconnect with {$c->name}", 'link' => "/clients/{$c->id}"];
        }

        return $actions;
    }

    /** Per-client actions for the client insight card (specs/15 + specs/04 front-office rules). */
    public static function forClient(\App\Models\Client $client, int $limit = 5): array
    {
        $actions = [];
        $overdue = \App\Models\Followup::where('client_id', $client->id)
            ->whereIn('status', ['overdue', 'escalated'])->orderBy('due_at')->limit($limit)->get();
        foreach ($overdue as $f) {
            $actions[] = ['kind' => 'followup_overdue', 'title' => "Clear overdue: {$f->title}", 'link' => '/followups?status=overdue'];
            if (count($actions) >= $limit) {
                return $actions;
            }
        }
        // Unfilled requirement: Client Management reports fewer deployed than the contract needs.
        $ful = ClientFulfillment::forClient($client);
        if ($ful['required'] > 0 && $ful['remaining'] > 0 && count($actions) < $limit) {
            $actions[] = [
                'kind' => 'staffing_gap',
                'title' => "Follow up on {$ful['remaining']} undeployed of {$ful['required']} required heads",
                'link' => "/clients/{$client->id}",
            ];
        }
        // Renewal approaching: active contract ends within 60 days.
        $renewing = \App\Models\Contract::where('client_id', $client->id)->where('status', 'active')
            ->get()->filter(function ($c) {
                if (! $c->start_date || ! $c->contract_months) {
                    return false;
                }
                $end = $c->start_date->copy()->addMonths($c->contract_months)->startOfDay();
                return $end->gte(now()->startOfDay()) && $end->lte(now()->addDays(60)->endOfDay());
            })->values();
        if ($renewing->isNotEmpty() && count($actions) < $limit) {
            $ref = $renewing->first()->ref ?? '#'.$renewing->first()->id;
            $actions[] = [
                'kind' => 'contract_renewal',
                'title' => "Begin renewal discussion — {$ref} ends soon",
                'link' => "/clients/{$client->id}",
            ];
        }
        $lowSurvey = \App\Models\Survey::where('client_id', $client->id)
            ->whereHas('responses', fn ($q) => $q->where('score', '<=', 6))->exists();
        if ($lowSurvey && count($actions) < $limit) {
            $actions[] = ['kind' => 'survey_low', 'title' => "Check in with {$client->name} about low score", 'link' => '/surveys'];
        }
        // Satisfaction drop: latest responded score fell 2+ points vs the previous one.
        $scores = \App\Models\SurveyResponse::whereHas('survey', fn ($q) => $q->where('client_id', $client->id))
            ->orderByDesc('responded_at')->limit(2)->pluck('score')->all();
        if (count($scores) === 2 && ($scores[1] - $scores[0]) >= 2 && count($actions) < $limit) {
            $actions[] = [
                'kind' => 'satisfaction_drop',
                'title' => "Satisfaction slipped {$scores[1]} → {$scores[0]} — call {$client->name}",
                'link' => '/surveys',
            ];
        }
        // Expansion: an existing (won-before) client has a fresh open opportunity.
        $hasWon = \App\Models\Opportunity::where('client_id', $client->id)->where('stage', 'won')->exists();
        $freshOpen = \App\Models\Opportunity::where('client_id', $client->id)
            ->whereNotIn('stage', ['won', 'lost'])->where('created_at', '>=', now()->subDays(30))->exists();
        if ($hasWon && $freshOpen && count($actions) < $limit) {
            $actions[] = [
                'kind' => 'expansion',
                'title' => "{$client->name} has a new requirement — support the expansion",
                'link' => "/clients/{$client->id}",
            ];
        }
        if ((! $client->last_contacted_at || $client->last_contacted_at->lt(now()->subDays(30))) && count($actions) < $limit) {
            $actions[] = ['kind' => 'client_stale', 'title' => "Reconnect with {$client->name}", 'link' => "/clients/{$client->id}"];
        }

        return $actions;
    }
}
