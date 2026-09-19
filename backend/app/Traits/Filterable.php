<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Generic list filters. Controllers allowlist per entity:
 *   Model::filter($request, ['status', 'owner_id'])->search($request->q, ['name', 'company_name'])
 */
trait Filterable
{
    public function scopeFilter(Builder $query, mixed $request, array $allowed): Builder
    {
        foreach ($allowed as $field) {
            $value = $request instanceof \Illuminate\Http\Request ? $request->query($field) : ($request[$field] ?? null);
            if ($value !== null && $value !== '') {
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

        return $query->where(function (Builder $q) use ($term, $columns, $table) {
            foreach ($columns as $col) {
                $q->orWhere($table.'.'.$col, 'ilike', "%{$term}%");
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
