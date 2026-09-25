<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($company->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $company->owner?->team_id === $user->team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $this->view($user, $company);
    }

    /** Ownership transfer: the owner, a same-team manager, or admin+. */
    public function transfer(User $user, Company $company): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($company->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $company->owner?->team_id === $user->team_id;
    }
}
