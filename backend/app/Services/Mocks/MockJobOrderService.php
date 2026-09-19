<?php

namespace App\Services\Mocks;

use App\Models\Opportunity;
use App\Services\Contracts\JobOrderServiceInterface;
use Illuminate\Support\Facades\Log;

/** Dept 1 mock. Agents: do NOT call Dept 1 directly — use this interface. */
class MockJobOrderService implements JobOrderServiceInterface
{
    public function pushWonOpportunity(Opportunity $opp): array
    {
        $ref = 'JO-2026-'.str_pad((string) $opp->id, 4, '0', STR_PAD_LEFT);
        Log::info('[mock] job order drafted', ['ref' => $ref, 'opp' => $opp->id]);

        return ['job_order_ref' => $ref, 'mock' => true];
    }

    public function deploymentStatus(int $clientId): array
    {
        $fixture = database_path('fixtures/headcount.json');

        return file_exists($fixture) ? (json_decode(file_get_contents($fixture), true)[$clientId] ?? ['deployed' => 0]) : ['deployed' => 0, 'mock' => true];
    }
}
