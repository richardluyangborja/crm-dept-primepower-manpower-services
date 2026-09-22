<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STATUSES = ['active', 'superseded', 'cancelled'];

    protected $fillable = [
        'opportunity_id', 'client_id', 'owner_id',
        'headcount', 'rate_per_head_centavos', 'contract_months',
        'monthly_billing_centavos', 'contract_total_centavos',
        'start_date', 'ref', 'status', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'headcount' => 'integer',
            'rate_per_head_centavos' => 'integer',
            'contract_months' => 'integer',
            'monthly_billing_centavos' => 'integer',
            'contract_total_centavos' => 'integer',
            'start_date' => 'date',
            'payload' => 'array',
        ];
    }

    public function opportunity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
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
