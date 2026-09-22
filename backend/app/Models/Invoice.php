<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'paid', 'overdue'];

    protected $fillable = [
        'client_id', 'job_order_id', 'opportunity_id', 'owner_id', 'ref', 'title',
        'amount_centavos', 'balance_centavos', 'status', 'due_at', 'payload',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'payload' => 'array'];
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function jobOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Dynamic overdue: sent + past due counts as overdue everywhere. */
    public function isOverdue(): bool
    {
        if ($this->status === 'paid') {
            return false;
        }
        if ($this->status === 'overdue') {
            return true;
        }

        return $this->due_at !== null && $this->due_at->isPast();
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue() || $this->due_at === null) {
            return 0;
        }

        // Carbon 3 diffInDays defaults to signed — force absolute aging.
        return now()->startOfDay()->diffInDays($this->due_at, true);
    }
}
