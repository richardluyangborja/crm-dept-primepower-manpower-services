<?php

namespace App\Http\Controllers;

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
        $leads = Lead::visibleToWithCompany($request->user())
            ->filter($request, ['status', 'source', 'owner_id', 'company_id'])
            ->search($request->query('q'), ['company_name', 'contact_name', 'contact_email'])
            ->latest()->paginate(min(100, (int) $request->query('per_page', 15)));

        return $this->paginated(LeadResource::collection($leads));
    }

    public function store(StoreLeadRequest $request, LeadService $service)
    {
        $this->authorize('create', Lead::class);
        $data = $request->validated();
        $user = $request->user();
        // Resolve or create the company first — it determines default ownership.
        if (! empty($data['company_id'])) {
            $company = \App\Models\Company::visibleTo($user)->findOrFail($data['company_id']);
        } else {
            // Reps own what they capture; managers/admins may assign to any active rep/manager.
            if ($user->role === 'sales_rep' || empty($data['owner_id'])) {
                $data['owner_id'] = $user->id;
            }
            $c = $data['company'] ?? [];
            $company = \App\Models\Company::create([
                'owner_id' => $data['owner_id'],
                'name' => $c['name'],
                'industry' => $c['industry'] ?? null,
                'address_city' => $c['address_city'] ?? null,
                'address_province' => $c['address_province'] ?? null,
                'contact_email' => $c['contact_email'] ?? $data['contact_email'] ?? null,
                'contact_phone' => $c['contact_phone'] ?? $data['contact_phone'] ?? null,
                'source' => $data['source'] ?? null,
            ]);
        }
        // One active lead per company — point at the open one instead (before moving anything).
        $open = $company->leads()->whereNotIn('status', ['unqualified', 'converted'])->first();
        if ($open) {
            return response()->json([
                'message' => "{$company->name} already has an open lead — work that one instead.",
                'errors' => ['company_id' => ['Company already has an open lead.']],
                'meta' => ['existing_lead_id' => $open->opaqueId()],
            ], 409);
        }
        // Ownership follows the company: new leads default to the company owner.
        // An explicit assignment by admin/manager moves the company too, so the
        // company owner always owns its pipeline (audited, like a transfer).
        $assigned = $user->role !== 'sales_rep' && ! empty($data['owner_id']);
        if ($assigned && (int) $data['owner_id'] !== (int) $company->owner_id) {
            $company->update(['owner_id' => $data['owner_id']]);
            $company->audit('owner_assigned', $user->id, ['to_owner_id' => $data['owner_id'], 'via' => 'lead_capture']);
        } elseif (! $assigned) {
            $data['owner_id'] = $company->owner_id;
        }
        $data['company_id'] = $company->id;
        $data['company_name'] = $data['company_name'] ?? $company->name;
        unset($data['company']);
        $duplicate = $service->findDuplicate($data);
        $lead = Lead::create($data);
        $service->score($lead);
        $lead->audit('created', $user->id, ['company_id' => $company->id]);
        $res = (new LeadResource($lead))->toArray($request);

        return response()->json(
            ['data' => $res, 'message' => 'Lead created — qualify it next.', 'meta' => ['duplicate_warning' => $duplicate]],
            201
        );
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $lead->load('owner:id,name');

        return $this->ok(new LeadResource($lead));
    }

    public function update(UpdateLeadRequest $request, Lead $lead, LeadService $service)
    {
        $this->authorize('update', $lead);
        $data = $request->validated();
        if (($data['status'] ?? null) === 'converted') {
            return $this->fail('Leads convert automatically when their deal is won — pick qualified or unqualified.', 422);
        }
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
}
