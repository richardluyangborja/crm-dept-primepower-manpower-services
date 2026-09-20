<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, Team $t): bool { return true; }
    public function create(User $u): bool { return in_array($u->role, ['superadmin', 'admin'], true); }
    public function update(User $u, Team $t): bool { return in_array($u->role, ['superadmin', 'admin'], true); }
    public function delete(User $u, Team $t): bool { return $u->role === 'superadmin'; }
}
