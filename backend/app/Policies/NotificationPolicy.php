<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/** Notifications are personal: owners read their own; elevated roles read all. */
class NotificationPolicy
{
    public function view(User $user, Notification $notification): bool
    {
        return in_array($user->role, ['superadmin', 'admin'], true)
            || $notification->user_id === $user->id;
    }
}
