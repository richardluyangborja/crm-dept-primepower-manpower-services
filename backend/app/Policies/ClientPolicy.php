<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($client->owner_id === $user->id || $client->company?->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && ($client->owner?->team_id === $user->team_id
                || $client->company?->owner?->team_id === $user->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->isElevated($user);
    }
}
