<?php

namespace App\Http\Controllers;

use App\Http\Resources\JobOrderResource;
use App\Models\JobOrder;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step v2-A (specs/18): visible mock job-order timeline per client. */
class JobOrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', JobOrder::class);
        $jobs = JobOrder::visibleTo($request->user())->with('client:id,name')
            ->filter($request, ['status', 'client_id', 'owner_id'])
            ->orderByDesc('id')->paginate(min(100, (int) $request->query('per_page', 25)));

        return $this->paginated(JobOrderResource::collection($jobs));
    }

    public function show(JobOrder $jobOrder)
    {
        $this->authorize('view', $jobOrder);
        $jobOrder->load('client:id,name');

        return $this->ok(new JobOrderResource($jobOrder));
    }

    /**
     * Front-office guard (specs/04): the CRM never advances core execution.
     * Progression is owned by Client Management; this endpoint stays read-only
     * so existing clients keep working while the UI no longer calls it.
     */
    public function advance(JobOrder $jobOrder)
    {
        $this->authorize('view', $jobOrder);

        return $this->fail('Job-order progression is handled by Client Management — the CRM shows read-only status.', 403);
    }
}
