<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    protected function isElevated(?User $user): bool
    {
        return $user && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }
        if ($invoice->owner_id === $user->id) {
            return true;
        }

        return $user->role === 'manager' && $user->team_id
            && $invoice->owner?->team_id === $user->team_id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        // Recording mock payments is a rep-level action (demo-friendly).
        return $this->view($user, $invoice);
    }
}
