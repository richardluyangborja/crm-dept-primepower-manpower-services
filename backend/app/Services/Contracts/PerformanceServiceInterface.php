<?php

namespace App\Services\Contracts;

interface PerformanceServiceInterface
{
    /**
     * Blended performance composite for one user (Core-2 performance, read-only mock).
     * $period format 'YYYY-MM'. Real CRM output blended with mock HR modifiers.
     */
    public function score(int $userId, string $period): array;

    /**
     * Team-pulse variant: slim per-member figures (composite, won value,
     * attendance) for many users in a bounded query set. Keys: mine, top.
     */
    public function pulse(array $userIds, int $requesterId, string $period): array;
}
