<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Followup extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STATUSES = ['open', 'done', 'snoozed', 'overdue', 'escalated'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'owner_id', 'client_id', 'company_id', 'opportunity_id', 'title', 'due_at',
        'priority', 'status', 'snoozed_until', 'escalated_to',
    ];

    protected $attributes = ['status' => 'open', 'priority' => 'medium'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'snoozed_until' => 'datetime'];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
