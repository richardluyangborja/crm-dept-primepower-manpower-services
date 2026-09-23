<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\JobOrder;
use App\Services\Contracts\WorkforceServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Read-only Core-1 staffing board (specs/05 hub). Advancing happens on client timelines only. */
class StaffingController extends Controller
{
    use ApiResponse;

    public function index(Request $request, WorkforceServiceInterface $workforce)
    {
        $clients = Client::visibleTo($request->user())->with('owner:id,name')->orderBy('name')->get();

        $rows = $clients->map(function ($client) use ($workforce) {
            $jobs = JobOrder::where('client_id', $client->id)->get();
            $hc = $workforce->headcountByClient($client->id);

            return [
                'client_id' => \App\Models\Client::encodeId($client->id),
                'client_name' => $client->name,
                'owner_name' => $client->owner?->name,
                'deployed' => $hc['deployed'] ?? 0,
                'site' => $hc['site'] ?? null,
                'job_orders' => $jobs->count(),
                'by_status' => $jobs->groupBy('status')->map->count(),
                'mock' => true,
            ];
        })->values()->all();

        return $this->ok([
            'data' => $rows,
            'meta' => [
                'total_deployed' => array_sum(array_column($rows, 'deployed')),
                'total_job_orders' => JobOrder::visibleTo($request->user())->count(),
                'mock' => true,
            ],
        ]);
    }
}
