<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'industry' => $this->industry,
            'address_city' => $this->address_city,
            'address_province' => $this->address_province,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'source' => $this->source,
            'open_leads_count' => $this->whenCounted('open_leads_count'),
            'leads_count' => $this->whenCounted('leads'),
            'clients_count' => $this->whenCounted('clients'),
            'opportunities_count' => $this->whenCounted('opportunities'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
