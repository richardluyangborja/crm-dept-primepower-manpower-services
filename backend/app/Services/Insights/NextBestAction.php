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

    /** Per-client actions for the client insight card (specs/15). */
    public static function forClient(\App\Models\Client $client, int $limit = 3): array
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
        $lowSurvey = \App\Models\Survey::where('client_id', $client->id)
            ->whereHas('responses', fn ($q) => $q->where('score', '<=', 6))->exists();
        if ($lowSurvey && count($actions) < $limit) {
            $actions[] = ['kind' => 'survey_low', 'title' => "Check in with {$client->name} about low score", 'link' => '/surveys'];
        }
        if ((! $client->last_contacted_at || $client->last_contacted_at->lt(now()->subDays(30))) && count($actions) < $limit) {
            $actions[] = ['kind' => 'client_stale', 'title' => "Reconnect with {$client->name}", 'link' => "/clients/{$client->id}"];
        }

        return $actions;
    }
}
