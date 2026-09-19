<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use Filterable, HasAuditLog, SoftDeletes;

    public const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];
    public const SOURCES = ['referral', 'walk_in', 'website', 'facebook', 'cold_call', 'event'];

    protected $fillable = [
        'owner_id', 'company_name', 'contact_name', 'contact_email',
        'contact_phone', 'source', 'status', 'score', 'notes', 'converted_client_id',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }
}
