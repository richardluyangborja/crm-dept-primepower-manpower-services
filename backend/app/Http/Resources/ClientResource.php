<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'industry' => $this->industry,
            'size_band' => $this->size_band,
            'address_city' => $this->address_city,
            'address_province' => $this->address_province,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status,
            'source' => $this->source,
            'created_from_lead_id' => \App\Models\Lead::encodeId($this->created_from_lead_id),
            'last_contacted_at' => $this->last_contacted_at,
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
