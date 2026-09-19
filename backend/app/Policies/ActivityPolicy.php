<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($activity->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $activity->owner?->team_id === $user->team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->view($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->isElevated($user);
    }
}
