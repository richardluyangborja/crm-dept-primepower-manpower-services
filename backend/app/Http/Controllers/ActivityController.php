<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Client;
use App\Services\ActivityService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 4 (specs/07): unified timeline loggers + templates. */
class ActivityController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Activity::class);
        $query = Activity::visibleTo($request->user())->with('client:id,name')
            ->filter($request, ['type', 'client_id', 'opportunity_id', 'owner_id', 'outcome'])
            ->search($request->query('q'), ['subject', 'body']);
        if ($request->query('from')) {
            $query->where('occurred_at', '>=', $request->query('from'));
        }
        if ($request->query('to')) {
            $query->where('occurred_at', '<=', $request->query('to'));
        }

        return $this->paginated(ActivityResource::collection($query->orderByDesc('occurred_at')->paginate(min(100, (int) $request->query('per_page', 25)))));
    }

    public function store(StoreActivityRequest $request, ActivityService $service)
    {
        $this->authorize('create', Activity::class);
        $data = $request->validated();
        $user = $request->user();
        $client = Client::visibleTo($user)->findOrFail($data['client_id']);
        if ($user->role === 'sales_rep' || empty($data['owner_id'])) {
            $data['owner_id'] = $user->id;
        }
        // Keep uploaded files (validated) alongside scalar input.
        $data['attachments'] = $request->file('attachments', []);
        ['activity' => $activity, 'followup' => $followup] = $service->log($data, $user->id);

        return response()->json([
            'data' => (new ActivityResource($activity))->toArray($request),
            'message' => $followup ? 'Logged — follow-up created too.' : 'Logged — timeline updated.',
            'meta' => ['followup_id' => $followup?->id, 'mock_note' => $data['type'] === 'email' ? 'Mock mode — no real email sent.' : null],
        ], 201);
    }

    public function show(Activity $activity)
    {
        $this->authorize('view', $activity);
        $activity->load('client:id,name');

        return $this->ok(new ActivityResource($activity));
    }

    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $this->authorize('update', $activity);
        $data = $request->validated();
        $activity->update($data);
        $activity->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new ActivityResource($activity->refresh()), 'Entry updated.');
    }

    public function destroy(Activity $activity)
    {
        $this->authorize('delete', $activity);
        $activity->delete();
        $activity->audit('deleted', auth('api')->id(), []);

        return $this->ok(null, 'Entry archived.');
    }

    public function templates(ActivityService $service)
    {
        return $this->ok($service->templates());
    }
}
