<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 3: personal notification inbox (bell badge source). */
class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Notification::query();
        if (! in_array($user->role, ['superadmin', 'admin'], true)) {
            $query->where('user_id', $user->id);
        }
        if ($request->query('unread')) {
            $query->whereNull('read_at');
        }

        return $this->paginated($query->latest()->paginate(min(100, (int) $request->query('per_page', 20))));
    }

    public function read(Notification $notification)
    {
        $this->authorize('view', $notification);
        $notification->update(['read_at' => now()]);

        return $this->ok(new NotificationResource($notification->refresh()), 'Marked as read.');
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->ok(null, 'Inbox cleared.');
    }
}
