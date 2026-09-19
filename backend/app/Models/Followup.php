<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Followup extends Model
{
    use Filterable, HasAuditLog, SoftDeletes;

    public const STATUSES = ['open', 'done', 'snoozed', 'overdue', 'escalated'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'owner_id', 'client_id', 'opportunity_id', 'title', 'due_at',
        'priority', 'status', 'snoozed_until', 'escalated_to',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'snoozed_until' => 'datetime'];
    }
}
