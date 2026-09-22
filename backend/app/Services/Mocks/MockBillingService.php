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
        // Front-office honesty (specs/04): sum the client's real open invoices.
        // Still labeled mock — live Finance would own this number.
        $outstanding = \App\Models\Invoice::where('client_id', $clientId)
            ->where('status', '!=', 'paid')->sum('balance_centavos');

        return [
            'outstanding_centavos' => (int) $outstanding,
            'status' => $outstanding > 0 ? 'has_balance' : 'current',
            'mock' => true,
        ];
    }
}
