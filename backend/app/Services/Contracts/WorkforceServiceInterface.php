<?php

namespace App\Services\Contracts;

interface WorkforceServiceInterface
{
    /** Headcount currently deployed per client (Dept 2 HR). */
    public function headcountByClient(int $clientId): array;
}
