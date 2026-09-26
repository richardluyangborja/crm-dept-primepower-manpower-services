<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/** Owner/team/admin scoping (specs/02). Superadmin bypasses everything. */
class LeadPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($lead->owner_id === $user->id || $lead->company?->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && ($lead->owner?->team_id === $user->team_id
                || $lead->company?->owner?->team_id === $user->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->isElevated($user);
    }

    public function assign(User $user): bool
    {
        return $user->role !== 'sales_rep';
    }
}
