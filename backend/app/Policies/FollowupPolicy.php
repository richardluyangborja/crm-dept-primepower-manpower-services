<?php

namespace App\Policies;

use App\Models\Followup;
use App\Models\User;

class FollowupPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Followup $followup): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($followup->owner_id === $user->id || $followup->company?->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && ($followup->owner?->team_id === $user->team_id
                || $followup->company?->owner?->team_id === $user->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Followup $followup): bool
    {
        return $this->view($user, $followup);
    }

    /** Reassignment: the owner, the owner's team manager, or admin+ (overhaul Phase 3). */
    public function reassign(User $user, Followup $followup): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($followup->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $followup->owner?->team_id === $user->team_id;
    }

    /** Escalation: sales reps only, on their own reminders (overhaul Phase 4). */
    public function escalate(User $user, Followup $followup): bool
    {
        return $user->role === 'sales_rep' && $followup->owner_id === $user->id;
    }

    public function delete(User $user, Followup $followup): bool
    {
        return $this->isElevated($user);
    }
}
