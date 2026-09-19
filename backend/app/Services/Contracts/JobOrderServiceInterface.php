<?php

namespace App\Services\Contracts;

use App\Models\Opportunity;

interface JobOrderServiceInterface
{
    /** Push a won opportunity to Dept 1. Returns ['job_order_ref' => 'JO-2026-XXXX']. */
    public function pushWonOpportunity(Opportunity $opp): array;

    /** Read-back deployment statuses for a client. */
    public function deploymentStatus(int $clientId): array;
}
