<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];
    public const SOURCES = ['facebook', 'gmail', 'phone', 'referral', 'walk_in', 'website', 'cold_call', 'event'];

    protected $fillable = [
        'owner_id', 'company_id', 'company_name', 'contact_name', 'contact_email',
        'contact_phone', 'headcount_needed', 'positions',
        'source', 'status', 'score', 'notes', 'converted_client_id',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer', 'headcount_needed' => 'integer'];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
