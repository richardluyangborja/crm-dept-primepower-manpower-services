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
        $query = Followup::visibleToWithCompany($request->user())->with(['client:id,name', 'escalatedTo:id,name'])
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
        $client = ! empty($data['client_id']) ? Client::visibleTo($user)->findOrFail($data['client_id']) : null;
        if (! empty($data['company_id'])) {
            $company = \App\Models\Company::visibleTo($user)->findOrFail($data['company_id']);
        } elseif ($client?->company_id) {
            $data['company_id'] = $client->company_id;
            $company = $client->company;
        } else {
            $company = null;
        }
        // Reps own what they set; admin/manager may assign, defaulting to the company owner
        // so the reminder lands on the rep who owns the account (overhaul Phase 3).
        $assigned = $user->role !== 'sales_rep' && ! empty($data['owner_id']);
        $data['owner_id'] = match (true) {
            $user->role === 'sales_rep' => $user->id,
            $assigned => $data['owner_id'],
            $company !== null => $company->owner_id,
            default => $user->id,
        };
        $data['priority'] ??= 'medium';
        $followup = Followup::create($data);
        $followup->audit('created', $user->id, ['client_id' => $client?->id]);

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
        // Moving a reminder to another rep is a reassignment: owner, team manager, or admin only.
        if (array_key_exists('owner_id', $data) && (int) $data['owner_id'] !== (int) $followup->owner_id) {
            $this->authorize('reassign', $followup);
            $followup->audit('reassigned', $request->user()->id, ['from_owner_id' => $followup->owner_id, 'to_owner_id' => $data['owner_id']]);
        }
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
        // Reps only, on their own reminders — escalation always goes to the team manager.
        $this->authorize('escalate', $followup);
        $manager = $service->teamManager($followup->owner);
        if (! $manager) {
            return $this->fail('No manager on your team to escalate to.', 422);
        }

        return $this->ok(
            new FollowupResource($service->escalate($followup, $manager->id, auth('api')->id())->load('escalatedTo:id,name')),
            "Escalated to {$manager->name}."
        );
    }
}
