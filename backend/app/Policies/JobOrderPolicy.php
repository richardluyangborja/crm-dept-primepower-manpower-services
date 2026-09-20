<?php

namespace App\Policies;

use App\Models\JobOrder;
use App\Models\User;

class JobOrderPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, JobOrder $job): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($job->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $job->owner?->team_id === $user->team_id;
    }

    public function update(User $user, JobOrder $job): bool
    {
        // Advancing the mock flow is a rep-level action (demo-friendly).
        return $this->view($user, $job);
    }
}
