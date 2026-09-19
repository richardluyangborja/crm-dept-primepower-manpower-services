<?php

namespace App\Services\Mocks;

use App\Models\Opportunity;
use App\Services\Contracts\BillingServiceInterface;
use Illuminate\Support\Facades\Log;

/** Dept 5 finance mock. */
class MockBillingService implements BillingServiceInterface
{
    public function createDraftInvoice(Opportunity $opp): array
    {
        $ref = 'INV-2026-'.str_pad((string) $opp->id, 4, '0', STR_PAD_LEFT);
        Log::info('[mock] draft invoice created', ['ref' => $ref, 'opp' => $opp->id]);

        return ['invoice_ref' => $ref, 'amount_centavos' => $opp->value_centavos, 'mock' => true];
    }

    public function paymentStatus(int $clientId): array
    {
        return ['outstanding_centavos' => 0, 'status' => 'current', 'mock' => true];
    }
}
