<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /** Slim list shape — full pack payload stays on show/generate responses. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'period_from' => $this->period_from,
            'period_to' => $this->period_to,
            'team_id' => $this->team_id,
            'file_path' => $this->file_path,
            'generated_by' => $this->generated_by,
            'created_at' => $this->created_at,
        ];
    }
}
