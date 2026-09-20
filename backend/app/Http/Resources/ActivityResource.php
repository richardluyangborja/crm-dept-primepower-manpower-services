<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'opportunity_id' => $this->opportunity_id,
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
