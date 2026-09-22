<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog;

    public const TYPES = ['call', 'email', 'meeting', 'site_visit', 'note'];

    protected $fillable = [
        'owner_id', 'client_id', 'opportunity_id', 'type',
        'subject', 'body', 'outcome', 'duration_minutes', 'occurred_at', 'attachments',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'attachments' => 'array'];
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
