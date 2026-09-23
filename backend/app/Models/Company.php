<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use Filterable, HasOpaqueId;

    protected $fillable = [
        'owner_id', 'name', 'industry', 'address_city', 'address_province',
        'contact_email', 'contact_phone', 'source',
    ];

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function leads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function opportunities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function followups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Followup::class);
    }
}
