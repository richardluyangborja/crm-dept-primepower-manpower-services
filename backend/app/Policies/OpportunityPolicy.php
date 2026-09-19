<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Opportunity $opp): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($opp->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $opp->owner?->team_id === $user->team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Opportunity $opp): bool
    {
        return $this->view($user, $opp);
    }

    public function delete(User $user, Opportunity $opp): bool
    {
        return $this->isElevated($user);
    }

    /** Reopening won/lost is manager+ only (specs/05). */
    public function reopen(User $user): bool
    {
        return $user->role !== 'sales_rep';
    }
}
