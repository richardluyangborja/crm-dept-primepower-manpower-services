<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use Filterable, HasOpaqueId;

    public const STATUSES = ['draft', 'sent', 'responded', 'expired'];

    protected $fillable = [
        'template_id', 'client_id', 'sent_by', 'channel', 'token', 'due_at', 'status',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function responses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'template_id');
    }

    /** Ownership = sent_by me OR my clients' surveys; managers see their team. */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, mixed $user): \Illuminate\Database\Eloquent\Builder
    {
        if (! $user || in_array($user->role, ['superadmin', 'admin'], true)) return $query;
        if ($user->role === 'manager' && $user->team_id) {
            $teamUserIds = User::where('team_id', $user->team_id)->pluck('id');
            $teamClientIds = Client::whereIn('owner_id', $teamUserIds)->pluck('id');
            return $query->where(fn ($q) => $q->whereIn('sent_by', $teamUserIds)->orWhereIn('client_id', $teamClientIds));
        }
        $myClientIds = Client::where('owner_id', $user->id)->pluck('id');
        return $query->where(fn ($q) => $q->where('sent_by', $user->id)->orWhereIn('client_id', $myClientIds));
    }
}
