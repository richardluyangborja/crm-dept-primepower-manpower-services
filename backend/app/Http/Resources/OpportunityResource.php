<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'owner_id' => $this->owner_id,
            'title' => $this->title,
            'stage' => $this->stage,
            'value_centavos' => $this->value_centavos,
            'probability' => $this->probability,
            'weighted_centavos' => (int) round($this->value_centavos * ($this->probability / 100)),
            'expected_close_date' => $this->expected_close_date,
            'lost_reason' => $this->lost_reason,
            'won_at' => $this->won_at,
            'lost_at' => $this->lost_at,
            'days_in_stage' => $this->updated_at?->diffInDays(now()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
