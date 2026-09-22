<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Generic list filters. Controllers allowlist per entity:
 *   Model::filter($request, ['status', 'owner_id'])->search($request->q, ['name', 'company_name'])
 */
trait Filterable
{
    /** Query fields carrying opaque public IDs (specs/14) → owning model. */
    protected const OPAQUE_FIELDS = [
        'client_id' => \App\Models\Client::class,
        'opportunity_id' => \App\Models\Opportunity::class,
        'contact_id' => \App\Models\Contact::class,
        'lead_id' => \App\Models\Lead::class,
        'job_order_id' => \App\Models\JobOrder::class,
    ];

    public function scopeFilter(Builder $query, mixed $request, array $allowed): Builder
    {
        foreach ($allowed as $field) {
            $value = $request instanceof \Illuminate\Http\Request ? $request->query($field) : ($request[$field] ?? null);
            if ($value !== null && $value !== '') {
                if (isset(static::OPAQUE_FIELDS[$field])) {
                    $decoded = static::OPAQUE_FIELDS[$field]::decodeId($value);
                    if ($decoded === null) {
                        return $query->whereRaw('1 = 0'); // tampered hash → empty, never 500
                    }
                    $value = $decoded;
                }
                $query->where($query->getModel()->getTable().'.'.$field, $value);
            }
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term, array $columns): Builder
    {
        if (! $term || $columns === []) {
            return $query;
        }
        $table = $query->getModel()->getTable();
        // ilike is Postgres-only; LIKE is case-insensitive enough on sqlite/mysql defaults.
        $op = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $columns, $table, $op) {
            foreach ($columns as $col) {
                $q->orWhere($table.'.'.$col, $op, "%{$term}%");
            }
        });
    }

    /** Owner/team/admin visibility. Managers see their team, reps see own rows. */
    public function scopeVisibleTo(Builder $query, mixed $user, string $ownerColumn = 'owner_id'): Builder
    {
        if (! $user || in_array($user->role, ['superadmin', 'admin'], true)) {
            return $query;
        }
        if ($user->role === 'manager' && $user->team_id) {
            $table = $query->getModel()->getTable();

            return $query->whereIn($table.'.'.$ownerColumn, function ($q) use ($user) {
                $q->select('id')->from('users')->where('team_id', $user->team_id);
            });
        }

        return $query->where($query->getModel()->getTable().'.'.$ownerColumn, $user->id);
    }
}
