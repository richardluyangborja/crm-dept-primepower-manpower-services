<?php

namespace App\Services\Contracts;

use App\Models\Opportunity;

interface BillingServiceInterface
{
    /** Create a draft AR invoice for a won opportunity. Returns ['invoice_ref' => 'INV-…']. */
    public function createDraftInvoice(Opportunity $opp): array;

    public function paymentStatus(int $clientId): array;
}
