<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 1 (specs/04): capture → qualify/score → convert. */
class LeadController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $leads = Lead::visibleTo($request->user())
            ->filter($request, ['status', 'source', 'owner_id'])
            ->search($request->query('q'), ['company_name', 'contact_name', 'contact_email'])
            ->latest()->paginate(min(100, (int) $request->query('per_page', 15)));

        return $this->paginated(LeadResource::collection($leads));
    }

    public function store(StoreLeadRequest $request, LeadService $service)
    {
        $this->authorize('create', Lead::class);
        $data = $request->validated();
        $user = $request->user();
        // Reps own what they capture; managers/admins may assign.
        if ($user->role === 'sales_rep' || empty($data['owner_id'])) {
            $data['owner_id'] = $user->id;
        }
        $duplicate = $service->findDuplicate($data);
        $lead = Lead::create($data);
        $service->score($lead);
        $lead->audit('created', $user->id, []);
        $res = (new LeadResource($lead))->toArray($request);

        return response()->json(
            ['data' => $res, 'message' => 'Lead created — qualify it next.', 'meta' => ['duplicate_warning' => $duplicate]],
            201
        );
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        return $this->ok(new LeadResource($lead));
    }

    public function update(UpdateLeadRequest $request, Lead $lead, LeadService $service)
    {
        $this->authorize('update', $lead);
        $data = $request->validated();
        if (isset($data['owner_id'])) {
            $this->authorize('assign', Lead::class);
        }
        $lead->update($data);
        if (array_intersect_key($data, array_flip(['contact_email', 'contact_phone', 'status', 'notes']))) {
            $service->score($lead->refresh());
        }
        $lead->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new LeadResource($lead->refresh()), 'Lead updated.');
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $lead->delete();
        $lead->audit('deleted', auth('api')->id(), []);

        return $this->ok(null, 'Lead archived.');
    }

    public function importTemplate()
    {
        $this->authorize('create', Lead::class);

        return response(
            app(\App\Services\LeadImportService::class)->template(),
            200,
            ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="leads-template.csv"']
        );
    }

    public function import(Request $request, \App\Services\LeadImportService $importer, LeadService $service)
    {
        $this->authorize('create', Lead::class);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $result = $importer->import($request->file('file'), $request->user()->id, $service);

        return $this->ok($result, "{$result['imported']} imported, ".count($result['failed'])." failed.");
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, LeadService $service)
    {
        $this->authorize('update', $lead);
        ['client' => $client, 'opportunity' => $opp] = $service->convertToClient($lead, $request->validated(), $request->user()->id);

        return $this->created(
            ['client_id' => $client->id, 'opportunity_id' => $opp?->id],
            'Lead converted — client created.'
        );
    }
}
