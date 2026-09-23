<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'owner_id' => $this->owner_id,
            'company_id' => \App\Models\Company::encodeId($this->company_id),
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name, $this->company_name),
            'contact_name' => $this->contact_name,
            'contact_position' => $this->contact_position,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'headcount_needed' => $this->headcount_needed,
            'positions' => $this->positions,
            'source' => $this->source,
            'status' => $this->status,
            'score' => $this->score,
            'notes' => $this->notes,
            'converted_client_id' => \App\Models\Client::encodeId($this->converted_client_id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
