<?php

namespace App\Services\Contracts;

interface PerformanceServiceInterface
{
    /**
     * Blended performance composite for one user (Core-2 performance, read-only mock).
     * $period format 'YYYY-MM'. Real CRM output blended with mock HR modifiers.
     */
    public function score(int $userId, string $period): array;
}
