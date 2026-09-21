<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($contract->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $contract->owner?->team_id === $user->team_id;
    }
}
