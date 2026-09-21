<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Read-only contract ledger (mock Core-3/Governance/Facilities). Signing happens via opportunity moves. */
class ContractController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Contract::class);
        $contracts = Contract::visibleTo($request->user())->with('client:id,name')
            ->filter($request, ['status', 'client_id', 'owner_id'])
            ->latest()->paginate(min(100, (int) $request->query('per_page', 25)));

        return $this->paginated(ContractResource::collection($contracts));
    }

    public function show(Contract $contract)
    {
        $this->authorize('view', $contract);
        $contract->load('client:id,name');

        return $this->ok(new ContractResource($contract));
    }
}
