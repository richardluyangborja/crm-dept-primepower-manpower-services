<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->opaqueId(),
            'ref' => $this->ref,
            'title' => $this->title,
            'client_id' => \App\Models\Client::encodeId($this->client_id),
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'job_order_id' => \App\Models\JobOrder::encodeId($this->job_order_id),
            'opportunity_id' => \App\Models\Opportunity::encodeId($this->opportunity_id),
            'owner_id' => $this->owner_id,
            'amount_centavos' => $this->amount_centavos,
            'balance_centavos' => $this->balance_centavos,
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->daysOverdue(),
            'due_at' => $this->due_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
