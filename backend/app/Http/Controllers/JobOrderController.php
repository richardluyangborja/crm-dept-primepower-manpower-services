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

    /** Advance exactly one step along draft → staffed → deployed → billed. */
    public function advance(JobOrder $jobOrder)
    {
        $this->authorize('update', $jobOrder);
        $next = JobOrder::FLOW[$jobOrder->status] ?? null;
        if (! $next) {
            return $this->fail('Job order is already fully billed — the journey is complete.', 422);
        }
        $from = $jobOrder->status;
        $jobOrder->update(['status' => $next]);
        $jobOrder->audit('advanced', auth('api')->id(), ['from' => $from, 'to' => $next]);

        return $this->ok(new JobOrderResource($jobOrder->refresh()), "Advanced to {$next}.");
    }
}
