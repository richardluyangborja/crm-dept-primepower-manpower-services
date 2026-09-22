<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STATUSES = ['prospect', 'active', 'inactive', 'blacklisted'];

    protected $fillable = [
        'owner_id', 'name', 'industry', 'size_band', 'address_city', 'address_province',
        'contact_email', 'contact_phone', 'status', 'source', 'created_from_lead_id', 'last_contacted_at',
    ];

    protected function casts(): array
    {
        return ['last_contacted_at' => 'datetime'];
    }

    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function opportunities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
