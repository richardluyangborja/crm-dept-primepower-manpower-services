<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'ref' => $this->ref,
            'opportunity_id' => \App\Models\Opportunity::encodeId($this->opportunity_id),
            'client_id' => \App\Models\Client::encodeId($this->client_id),
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'owner_id' => $this->owner_id,
            'headcount' => $this->headcount,
            'rate_per_head_centavos' => $this->rate_per_head_centavos,
            'contract_months' => $this->contract_months,
            'monthly_billing_centavos' => $this->monthly_billing_centavos,
            'contract_total_centavos' => $this->contract_total_centavos,
            'start_date' => $this->start_date,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
