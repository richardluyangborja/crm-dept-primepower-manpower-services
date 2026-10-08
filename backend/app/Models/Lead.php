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

    /**
     * Agency manpower allowlist: only low-level deployable roles.
     * No head/manager/director positions on deployed staff.
     * `contact_position` (client contact title) stays free-text — only
     * `positions` (the manpower need) is restricted to this list.
     */
    public const LOW_LEVEL_POSITIONS = [
        'security guards', 'guards', 'janitors', 'housekeepers',
        'packers', 'production aides', 'production crew', 'cannery workers',
        'cashiers', 'sales clerks', 'merchandisers', 'promo staff',
        'waiters', 'porters', 'stevedores', 'forklift operators',
        'warehouse aides', 'drivers', 'messengers', 'tellers',
        'ward aides', 'nursing aides', 'lab aides', 'farm aides',
        'technicians', 'housekeeping aides', 'service crew', 'commissary crew',
        'helpers', 'utility staff',
    ];

    protected $fillable = [
        'owner_id', 'company_id', 'company_name', 'contact_name', 'contact_position', 'contact_email',
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
