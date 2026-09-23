<?php

namespace App\Services\Contracts;

interface AttendanceServiceInterface
{
    /**
     * Month attendance for one user (Core-2 timekeeping, read-only mock).
     * $month format 'YYYY-MM'. Returns days + summary + leave balances.
     */
    public function monthly(int $userId, string $month): array;
}
