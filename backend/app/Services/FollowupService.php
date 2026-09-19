<?php

namespace App\Services;

use App\Models\Followup;
use App\Models\User;
use App\Services\Contracts\NotifyServiceInterface;

/** Follow-up lifecycle (specs/08): done / snooze / escalate + scheduler dispatch. */
class FollowupService
{
    public function __construct(protected NotifyServiceInterface $notify) {}

    public function complete(Followup $followup, int $actorId): Followup
    {
        $followup->update(['status' => 'done', 'snoozed_until' => null]);
        $followup->audit('completed', $actorId, []);

        return $followup->refresh();
    }

    public function snooze(Followup $followup, string $until, int $actorId): Followup
    {
        $followup->update(['status' => 'snoozed', 'snoozed_until' => $until]);
        $followup->audit('snoozed', $actorId, ['until' => $until]);

        return $followup->refresh();
    }

    /** Manual escalation (auto path lives in DispatchDueReminders). */
    public function escalate(Followup $followup, ?int $toUserId, int $actorId): Followup
    {
        $to = $toUserId ?? $this->teamManagerId($followup->owner);
        $followup->update(['status' => 'escalated', 'escalated_to' => $to]);
        $followup->audit('escalated', $actorId, ['to' => $to]);
        if ($to) {
            $this->notify->send($to, 'escalation', "Escalated: {$followup->title}", 'An overdue follow-up needs you.', '/followups?status=escalated');
        }

        return $followup->refresh();
    }

    /**
     * Scheduler pass (runs every minute via reminders:dispatch):
     * - due within 60 min and still open → remind owner once (notified_at guard via audit? we use a meta flag on notifications dedupe by followup+kind in last 2h).
     * - past due and open/snoozed(expired) → overdue (+ owner notice).
     * - overdue > 24h → notify owner + team manager.
     * - overdue > 72h and not escalated → auto-escalate to team manager.
     *
     * @return array{reminded: int, overdue: int, escalated: int}
     */
    public function dispatch(): array
    {
        $counts = ['reminded' => 0, 'overdue' => 0, 'escalated' => 0];
        $now = now();

        // Due soon.
        $dueSoon = Followup::where('status', 'open')->whereBetween('due_at', [$now, $now->copy()->addHour()])->get();
        foreach ($dueSoon as $f) {
            if (! $this->recentlyNotified($f, 'due_soon', 120)) {
                $this->notify->send($f->owner_id, 'reminder', "Due soon: {$f->title}", 'Due within the hour.', '/followups');
                $f->audit('reminded', null, ['kind' => 'due_soon']);
                $counts['reminded']++;
            }
        }

        // Flip to overdue (incl. expired snoozes).
        $pastDue = Followup::whereIn('status', ['open', 'snoozed'])
            ->where(function ($q) use ($now) {
                $q->where('due_at', '<', $now)
                    ->orWhere(fn ($qq) => $qq->whereNotNull('snoozed_until')->where('snoozed_until', '<', $now));
            })->get();
        foreach ($pastDue as $f) {
            $was = $f->status;
            $f->update(['status' => 'overdue']);
            $f->audit('overdue', null, ['from' => $was]);
            $counts['overdue']++;
            if (! $this->recentlyNotified($f, 'overdue', 60 * 20)) {
                $this->notify->send($f->owner_id, 'overdue', "Overdue: {$f->title}", 'Clear it or snooze with a plan.', '/followups?status=overdue');
            }
        }

        // 24h+ overdue → owner + manager nudge (once per day).
        $stale = Followup::where('status', 'overdue')->where('due_at', '<', $now->copy()->subDay())->get();
        foreach ($stale as $f) {
            if ($this->recentlyNotified($f, 'stale_24h', 60 * 20)) {
                continue;
            }
            $mgr = $this->teamManagerId($f->owner);
            $this->notify->send($f->owner_id, 'stale', "Still overdue (24h+): {$f->title}", 'Your manager has been nudged too.', '/followups?status=overdue');
            if ($mgr && $mgr !== $f->owner_id) {
                $this->notify->send($mgr, 'stale', "Team overdue (24h+): {$f->title}", "Owner: {$f->owner?->name}.", '/followups?status=overdue');
            }
            $f->audit('stale_nudged', null, ['manager_id' => $mgr]);
        }

        // 72h+ overdue → auto-escalate.
        $critical = Followup::where('status', 'overdue')->where('due_at', '<', $now->copy()->subHours(72))->get();
        foreach ($critical as $f) {
            $mgr = $this->teamManagerId($f->owner);
            if (! $mgr) {
                continue;
            }
            $f->update(['status' => 'escalated', 'escalated_to' => $mgr]);
            $f->audit('auto_escalated', null, ['to' => $mgr]);
            $this->notify->send($mgr, 'escalation', "Auto-escalated (72h+): {$f->title}", "Owner: {$f->owner?->name}.", '/followups?status=escalated');
            $counts['escalated']++;
        }

        return $counts;
    }

    protected function teamManagerId(?User $owner): ?int
    {
        if (! $owner?->team_id) {
            return null;
        }

        return User::where('team_id', $owner->team_id)->where('role', 'manager')->value('id');
    }

    /** Dedupe repeat notices: any audit row with meta.kind in the last $minutes. */
    protected function recentlyNotified(Followup $followup, string $kind, int $minutes): bool
    {
        return \App\Models\AuditLog::where('entity', 'followups')->where('entity_id', $followup->id)
            ->where('meta->kind', $kind)->where('created_at', '>', now()->subMinutes($minutes))->exists();
    }
}
