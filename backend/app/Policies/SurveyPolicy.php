<?php

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;

class SurveyPolicy
{
    protected function isElevated(?User $u): bool { return in_array($u->role, ['superadmin', 'admin'], true); }

    public function viewAny(User $u): bool { return true; }

    public function view(User $u, Survey $s): bool
    {
        if ($this->isElevated($u)) return true;
        if ($s->sent_by === $u->id) return true;
        // rep sees surveys for their clients
        if ($s->client?->owner_id === $u->id) return true;
        return $u->role === 'manager' && $u->team_id && $s->client?->owner?->team_id === $u->team_id;
    }

    public function create(User $u): bool { return true; }

    public function update(User $u, Survey $s): bool { return $this->view($u, $s); }

    public function delete(User $u, Survey $s): bool { return $this->isElevated($u); }
}
