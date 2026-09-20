<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    protected function isAdmin(?User $u): bool { return in_array($u->role, ['superadmin', 'admin'], true); }

    public function viewAny(User $u): bool
    {
        return $this->isAdmin($u) || $u->role === 'manager';
    }

    public function view(User $u, User $target): bool
    {
        if ($u->id === $target->id || $this->isAdmin($u)) return true;
        return $u->role === 'manager' && $u->team_id && $target->team_id === $u->team_id;
    }

    public function create(User $u): bool { return $this->isAdmin($u); }
    public function update(User $u, ?User $t = null): bool { return $this->isAdmin($u); }
    public function deactivate(User $u, ?User $t = null): bool { return $this->isAdmin($u); }
    public function resetPassword(User $u, ?User $t = null): bool { return $this->isAdmin($u); }
}
