<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOrder extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STATUSES = ['draft', 'staffed', 'deployed', 'billed'];
    public const FLOW = ['draft' => 'staffed', 'staffed' => 'deployed', 'deployed' => 'billed'];

    protected $fillable = [
        'client_id', 'opportunity_id', 'owner_id', 'ref', 'title', 'headcount',
        'value_centavos', 'status', 'invoice_ref', 'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function opportunity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // Ownership follows the client so team scoping stays consistent.
        return $this->belongsTo(User::class, 'owner_id');
    }
}
