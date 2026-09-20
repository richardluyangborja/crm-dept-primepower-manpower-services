<?php

namespace App\Http\Controllers;

use App\Http\Requests\SnoozeFollowupRequest;
use App\Http\Requests\StoreFollowupRequest;
use App\Http\Requests\UpdateFollowupRequest;
use App\Http\Resources\FollowupResource;
use App\Models\Client;
use App\Models\Followup;
use App\Services\FollowupService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 3 (specs/08): reminders queue + lifecycle + escalation. */
class FollowupController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Followup::class);
        $query = Followup::visibleTo($request->user())->with('client:id,name')
            ->filter($request, ['status', 'priority', 'owner_id', 'client_id']);
        if ($request->query('due_from')) {
            $query->where('due_at', '>=', $request->query('due_from'));
        }
        if ($request->query('due_to')) {
            $query->where('due_at', '<=', $request->query('due_to'));
        }
        if ($request->query('due_today')) {
            $query->whereDate('due_at', today());
        }

        return $this->paginated(FollowupResource::collection($query->orderBy('due_at')->paginate(min(100, (int) $request->query('per_page', 50)))));
    }

    public function store(StoreFollowupRequest $request)
    {
        $this->authorize('create', Followup::class);
        $data = $request->validated();
        $user = $request->user();
        $client = Client::visibleTo($user)->findOrFail($data['client_id']);
        if ($user->role === 'sales_rep' || empty($data['owner_id'])) {
            $data['owner_id'] = $user->id;
        }
        $data['priority'] ??= 'medium';
        $followup = Followup::create($data);
        $followup->audit('created', $user->id, ['client_id' => $client->id]);

        return $this->created(new FollowupResource($followup), 'Reminder set — we\'ll notify you.');
    }

    public function show(Followup $followup)
    {
        $this->authorize('view', $followup);
        $followup->load('client:id,name');

        return $this->ok(new FollowupResource($followup));
    }

    public function update(UpdateFollowupRequest $request, Followup $followup)
    {
        $this->authorize('update', $followup);
        $data = $request->validated();
        $followup->update($data);
        $followup->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new FollowupResource($followup->refresh()), 'Reminder updated.');
    }

    public function destroy(Followup $followup)
    {
        $this->authorize('delete', $followup);
        $followup->delete();
        $followup->audit('deleted', auth('api')->id(), []);

        return $this->ok(null, 'Reminder archived.');
    }

    public function done(Followup $followup, FollowupService $service)
    {
        $this->authorize('update', $followup);

        return $this->ok(new FollowupResource($service->complete($followup, auth('api')->id())), 'Done — nice.');
    }

    public function snooze(SnoozeFollowupRequest $request, Followup $followup, FollowupService $service)
    {
        $this->authorize('update', $followup);

        return $this->ok(
            new FollowupResource($service->snooze($followup, $request->validated()['snoozed_until'], auth('api')->id())),
            'Snoozed.'
        );
    }

    public function escalate(Request $request, Followup $followup, FollowupService $service)
    {
        $this->authorize('update', $followup);
        $request->validate(['to_user_id' => ['sometimes', 'exists:users,id']]);

        return $this->ok(
            new FollowupResource($service->escalate($followup, $request->input('to_user_id'), auth('api')->id())),
            'Escalated.'
        );
    }
}
