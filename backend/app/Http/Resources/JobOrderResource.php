<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'ref' => $this->ref,
            'title' => $this->title,
            'client_id' => \App\Models\Client::encodeId($this->client_id),
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'opportunity_id' => \App\Models\Opportunity::encodeId($this->opportunity_id),
            'owner_id' => $this->owner_id,
            'headcount' => $this->headcount,
            'value_centavos' => $this->value_centavos,
            'status' => $this->status,
            'next_status' => \App\Models\JobOrder::FLOW[$this->status] ?? null,
            'invoice_ref' => $this->invoice_ref,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
