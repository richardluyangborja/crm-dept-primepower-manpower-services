<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'opportunity_id' => $this->opportunity_id,
            'title' => $this->title,
            'due_at' => $this->due_at,
            'priority' => $this->priority,
            'status' => $this->status,
            'snoozed_until' => $this->snoozed_until,
            'escalated_to' => $this->escalated_to,
            'is_overdue' => in_array($this->status, ['overdue', 'escalated'], true)
                || ($this->due_at && $this->due_at->isPast() && ! in_array($this->status, ['done'], true)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
