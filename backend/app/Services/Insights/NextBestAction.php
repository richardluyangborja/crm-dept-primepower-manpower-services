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
}
