<?php

namespace App\Services\Mocks;

use App\Services\Contracts\WorkforceServiceInterface;

/** Dept 2 HR mock. */
class MockWorkforceService implements WorkforceServiceInterface
{
    public function headcountByClient(int $clientId): array
    {
        $fixture = database_path('fixtures/headcount.json');
        if (file_exists($fixture)) {
            $all = json_decode(file_get_contents($fixture), true);

            return $all[$clientId] ?? ['deployed' => 0, 'mock' => true];
        }

        return ['deployed' => 0, 'mock' => true];
    }
}
