<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'template_name' => $this->whenLoaded('template', fn () => $this->template?->name),
            'template_type' => $this->whenLoaded('template', fn () => $this->template?->type),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'sent_by' => $this->sent_by,
            'channel' => $this->channel,
            'token' => $this->token,
            'share_url' => '/s/'.$this->token,
            'due_at' => $this->due_at,
            'status' => $this->status,
            'response' => $this->whenLoaded('responses', fn () => $this->responses->first() ? [
                'score' => $this->responses->first()->score,
                'comment' => $this->responses->first()->comment,
                'responded_at' => $this->responses->first()->responded_at,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
