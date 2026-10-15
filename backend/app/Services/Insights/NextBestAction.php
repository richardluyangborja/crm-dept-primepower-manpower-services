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
            $actions[] = ['kind' => 'client_stale', 'title' => "Reconnect with {$c->name}", 'link' => "/clients/{$c->opaqueId()}"];
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
                'link' => "/clients/{$client->opaqueId()}",
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
                'link' => "/clients/{$client->opaqueId()}",
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
                'link' => "/clients/{$client->opaqueId()}",
            ];
        }
        if ((! $client->last_contacted_at || $client->last_contacted_at->lt(now()->subDays(30))) && count($actions) < $limit) {
            $actions[] = ['kind' => 'client_stale', 'title' => "Reconnect with {$client->name}", 'link' => "/clients/{$client->id}"];
        }

        return $actions;
    }

    /**
     * Bulk variant of forClient for report-scale loops: all inputs are
     * pre-fetched in ~8 queries total instead of ~8 per client. Same rules,
     * same order, same early-stop at $limit. Score-drop detection looks at
     * the trailing 12 months (older history is noise for a pack).
     *
     * @param  array<int, array{id: int, name: string, last_contacted_at: mixed}>  $clients keyed by id
     * @return array<int, list<array{kind: string, title: string, link: string}>> keyed by client id
     */
    public static function forClients(array $clients, int $limit = 5): array
    {
        $ids = array_keys($clients);
        if ($ids === []) {
            return [];
        }

        $overdue = \App\Models\Followup::whereIn('client_id', $ids)
            ->whereIn('status', ['overdue', 'escalated'])->orderBy('due_at')
            ->get(['client_id', 'title'])->groupBy('client_id');

        $required = \App\Models\Contract::whereIn('client_id', $ids)->where('status', 'active')
            ->selectRaw('client_id, COALESCE(SUM(headcount),0) as heads')->groupBy('client_id')->pluck('heads', 'client_id');
        $fallback = \App\Models\JobOrder::whereIn('client_id', $ids)->whereNotIn('status', ['billed'])
            ->selectRaw('client_id, COALESCE(SUM(headcount),0) as heads')->groupBy('client_id')->pluck('heads', 'client_id');

        $fixturePath = database_path('fixtures/headcount.json');
        $fixture = is_file($fixturePath) ? (json_decode(file_get_contents($fixturePath), true) ?: []) : [];

        $contracts = \App\Models\Contract::whereIn('client_id', $ids)->where('status', 'active')
            ->get(['client_id', 'ref', 'start_date', 'contract_months'])->groupBy('client_id');

        $lowSet = \App\Models\Survey::whereIn('client_id', $ids)
            ->whereHas('responses', fn ($q) => $q->where('score', '<=', 6))
            ->distinct()->pluck('client_id')->flip();

        $scores = \App\Models\SurveyResponse::whereHas('survey', fn ($q) => $q->whereIn('client_id', $ids))
            ->where('responded_at', '>=', now()->subMonths(12))
            ->orderByDesc('responded_at')->get(['survey_id', 'score']);
        $surveyClient = \App\Models\Survey::whereIn('client_id', $ids)->pluck('client_id', 'id');
        $lastTwo = [];
        foreach ($scores as $r) {
            $cid = $surveyClient[$r->survey_id] ?? null;
            if ($cid !== null && count($lastTwo[$cid] ?? []) < 2) {
                $lastTwo[$cid][] = $r->score;
            }
        }

        $wonSet = \App\Models\Opportunity::whereIn('client_id', $ids)->where('stage', 'won')
            ->distinct()->pluck('client_id')->flip();
        $freshSet = \App\Models\Opportunity::whereIn('client_id', $ids)
            ->whereNotIn('stage', ['won', 'lost'])->where('created_at', '>=', now()->subDays(30))
            ->distinct()->pluck('client_id')->flip();

        $out = [];
        foreach ($clients as $id => $c) {
            $actions = [];
            foreach (($overdue[$id] ?? collect()) as $f) {
                $actions[] = ['kind' => 'followup_overdue', 'title' => "Clear overdue: {$f->title}", 'link' => '/followups?status=overdue'];
                if (count($actions) >= $limit) {
                    break;
                }
            }
            if (count($actions) < $limit) {
                $req = (int) ($required[$id] ?? 0);
                if ($req === 0) {
                    $req = (int) ($fallback[$id] ?? 0);
                }
                $dep = (int) ($fixture[$id]['deployed'] ?? 0);
                if ($req > 0 && max(0, $req - $dep) > 0) {
                    $actions[] = [
                        'kind' => 'staffing_gap',
                        'title' => 'Follow up on '.max(0, $req - $dep)." undeployed of {$req} required heads",
                        'link' => '/clients/'.\App\Models\Client::encodeId($id),
                    ];
                }
            }
            if (count($actions) < $limit) {
                $renewing = ($contracts[$id] ?? collect())->filter(function ($ct) {
                    if (! $ct->start_date || ! $ct->contract_months) {
                        return false;
                    }
                    $end = $ct->start_date->copy()->addMonths($ct->contract_months)->startOfDay();
                    return $end->gte(now()->startOfDay()) && $end->lte(now()->addDays(60)->endOfDay());
                })->values();
                if ($renewing->isNotEmpty()) {
                    $actions[] = [
                        'kind' => 'contract_renewal',
                        'title' => 'Begin renewal discussion — '.($renewing->first()->ref ?? '#'.$id).' ends soon',
                        'link' => '/clients/'.\App\Models\Client::encodeId($id),
                    ];
                }
            }
            if (isset($lowSet[$id]) && count($actions) < $limit) {
                $actions[] = ['kind' => 'survey_low', 'title' => "Check in with {$c['name']} about low score", 'link' => '/surveys'];
            }
            if (count($actions) < $limit) {
                $pair = $lastTwo[$id] ?? [];
                if (count($pair) === 2 && ($pair[1] - $pair[0]) >= 2) {
                    $actions[] = [
                        'kind' => 'satisfaction_drop',
                        'title' => "Satisfaction slipped {$pair[1]} → {$pair[0]} — call {$c['name']}",
                        'link' => '/surveys',
                    ];
                }
            }
            if (isset($wonSet[$id]) && isset($freshSet[$id]) && count($actions) < $limit) {
                $actions[] = [
                    'kind' => 'expansion',
                    'title' => "{$c['name']} has a new requirement — support the expansion",
                    'link' => '/clients/'.\App\Models\Client::encodeId($id),
                ];
            }
            $stale = empty($c['last_contacted_at']) || \Carbon\Carbon::parse($c['last_contacted_at'])->lt(now()->subDays(30));
            if ($stale && count($actions) < $limit) {
                $actions[] = ['kind' => 'client_stale', 'title' => "Reconnect with {$c['name']}", 'link' => '/clients/'.\App\Models\Client::encodeId($id)];
            }
            $out[$id] = $actions;
        }

        return $out;
    }
}
