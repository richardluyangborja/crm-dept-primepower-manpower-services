<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'client_id' => \App\Models\Client::encodeId($this->client_id),
            'full_name' => $this->full_name,
            'position' => $this->position,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_primary' => (bool) $this->is_primary,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
        ];
    }
}
