<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use Filterable;

    public const STATUSES = ['draft', 'sent', 'responded', 'expired'];

    protected $fillable = [
        'template_id', 'client_id', 'sent_by', 'channel', 'token', 'due_at', 'status',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function responses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
