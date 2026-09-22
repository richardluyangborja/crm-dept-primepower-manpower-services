<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'owner_id' => $this->owner_id,
            'client_id' => \App\Models\Client::encodeId($this->client_id),
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'opportunity_id' => \App\Models\Opportunity::encodeId($this->opportunity_id),
            'type' => $this->type,
            'subject' => $this->subject,
            'body' => $this->body,
            'outcome' => $this->outcome,
            'duration_minutes' => $this->duration_minutes,
            'occurred_at' => $this->occurred_at,
            'attachments' => collect($this->attachments ?? [])->map(fn ($a) => collect($a)->except('path'))->values(),
            'created_at' => $this->created_at,
        ];
    }
}
