<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'client_id' => \App\Models\Client::encodeId($this->client_id),
            'company_id' => \App\Models\Company::encodeId($this->company_id),
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'owner_id' => $this->owner_id,
            'title' => $this->title,
            'stage' => $this->stage,
            'value_centavos' => $this->value_centavos,
            'headcount' => $this->headcount,
            'rate_per_head_centavos' => $this->rate_per_head_centavos,
            'contract_months' => $this->contract_months,
            'monthly_billing_centavos' => $this->monthlyBilling(),
            'contract_total_centavos' => $this->contractTotal(),
            'probability' => $this->probability,
            'weighted_centavos' => (int) round($this->value_centavos * ($this->probability / 100)),
            'expected_close_date' => $this->expected_close_date,
            'lost_reason' => $this->lost_reason,
            'won_at' => $this->won_at,
            'lost_at' => $this->lost_at,
            'days_in_stage' => $this->updated_at ? (int) floor(abs($this->updated_at->diffInDays(now()))) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
