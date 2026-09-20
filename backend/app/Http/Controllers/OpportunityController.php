<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveStageRequest;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Http\Resources\OpportunityResource;
use App\Models\Client;
use App\Models\Opportunity;
use App\Services\OpportunityService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 2 (specs/05): pipeline CRUD + guarded stage moves + win/loss. */
class OpportunityController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);
        $opps = Opportunity::visibleTo($request->user())->with('client:id,name')
            ->filter($request, ['stage', 'client_id', 'owner_id'])
            ->search($request->query('q'), ['title'])
            ->orderBy('expected_close_date')->paginate(min(100, (int) $request->query('per_page', 50)));

        return $this->paginated(OpportunityResource::collection($opps));
    }

    public function store(StoreOpportunityRequest $request)
    {
        $this->authorize('create', Opportunity::class);
        $data = $request->validated();
        $user = $request->user();
        // Opp owner must be able to see the client; reps own what they create.
        $client = Client::visibleTo($user)->findOrFail($data['client_id']);
        if ($user->role === 'sales_rep' || empty($data['owner_id'])) {
            $data['owner_id'] = $user->id;
        }
        $data['stage'] ??= 'new';
        $data['probability'] ??= Opportunity::STAGE_PROBABILITY[$data['stage']];
        $opp = Opportunity::create($data);
        $opp->audit('created', $user->id, ['client_id' => $client->id]);

        return $this->created(new OpportunityResource($opp), 'Opportunity created.');
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);
        $opportunity->load('client:id,name');

        return $this->ok(new OpportunityResource($opportunity));
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $data = $request->validated();
        $opportunity->update($data);
        $opportunity->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new OpportunityResource($opportunity->refresh()), 'Opportunity updated.');
    }

    public function destroy(Opportunity $opportunity)
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();
        $opportunity->audit('deleted', auth('api')->id(), []);

        return $this->ok(null, 'Opportunity archived.');
    }

    public function move(MoveStageRequest $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $user = $request->user();
        $opp = $service->moveStage($opportunity, $request->validated(), $user->id, $user->role);
        $opp->load('client:id,name');

        return $this->ok(new OpportunityResource($opp), "Moved to {$opp->stage}.");
    }

    public function win(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $user = $request->user();
        $validated = validator(
            ['stage' => 'won'] + $request->only(['probability']),
            (new MoveStageRequest)->rules(),
            (new MoveStageRequest)->messages()
        )->validate();
        $opp = $service->moveStage($opportunity, $validated, $user->id, $user->role);
        $opp->load('client:id,name');

        return $this->ok(new OpportunityResource($opp), 'Marked as won — job order + invoice drafted (mock).');
    }

    public function lose(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $user = $request->user();
        $validated = validator(
            ['stage' => 'lost'] + $request->only(['lost_reason', 'probability']),
            (new MoveStageRequest)->rules(),
            (new MoveStageRequest)->messages()
        )->validate();
        $opp = $service->moveStage($opportunity, $validated, $user->id, $user->role);
        $opp->load('client:id,name');

        return $this->ok(new OpportunityResource($opp), 'Marked as lost.');
    }
}
