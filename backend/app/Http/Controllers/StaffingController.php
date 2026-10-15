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
        // Paged per-client rows: 1 client page + 1 grouped job query +
        // 1 fixture read, no matter the book size. Totals stay global.
        $query = Client::visibleTo($request->user())->orderBy('name');
        if ($request->query('client_id') && ($cid = Client::decodeId($request->query('client_id')))) {
            $query->where('clients.id', $cid);
        }
        $clients = $query->with('owner:id,name')->paginate(min(100, (int) $request->query('per_page', 15)));
        $ids = $clients->getCollection()->pluck('id');

        $jobsByClient = $ids->isEmpty() ? collect() : JobOrder::whereIn('client_id', $ids)
            ->selectRaw('client_id, status, COUNT(*) as count')->groupBy('client_id', 'status')
            ->get()->groupBy('client_id');

        $fixturePath = database_path('fixtures/headcount.json');
        $fixture = is_file($fixturePath) ? (json_decode(file_get_contents($fixturePath), true) ?: []) : [];

        $rows = $clients->getCollection()->map(function ($client) use ($jobsByClient, $fixture) {
            $groups = $jobsByClient[$client->id] ?? collect();
            $hc = $fixture[$client->id] ?? ['deployed' => 0];

            return [
                'client_id' => \App\Models\Client::encodeId($client->id),
                'client_name' => $client->name,
                'owner_name' => $client->owner?->name,
                'deployed' => $hc['deployed'] ?? 0,
                'site' => $hc['site'] ?? null,
                'job_orders' => $groups->sum('count'),
                'by_status' => $groups->pluck('count', 'status'),
                'mock' => true,
            ];
        })->values()->all();

        // Global totals (single cheap queries, no row loading).
        $visibleIds = Client::visibleTo($request->user())->pluck('id');
        $totalDeployed = 0;
        foreach ($visibleIds as $id) {
            $totalDeployed += (int) ($fixture[$id]['deployed'] ?? 0);
        }

        return $this->ok([
            'data' => $rows,
            'meta' => [
                'total_deployed' => $totalDeployed,
                'total_job_orders' => JobOrder::visibleTo($request->user())->count(),
                'page' => $clients->currentPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'mock' => true,
            ],
        ]);
    }
}
