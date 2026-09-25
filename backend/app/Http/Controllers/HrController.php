<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Contracts\AttendanceServiceInterface;
use App\Services\Contracts\PerformanceServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Phase H2 Core-2 integration surface (specs/11): read-only HR data.
 * Roles: reps see self; managers see self + same team; admin/superadmin see
 * all managers + sales. No performance rows exist for admin/superadmin.
 * Nothing here mutates — GET only.
 */
class HrController extends Controller
{
    use ApiResponse;

    /** Resolve + authorize the target user for HR reads. */
    protected function target(Request $request): User
    {
        $me = $request->user();
        $target = $me;
        if ($request->query('user_id') !== null) {
            $target = User::findOrFail((int) $request->query('user_id'));
        }
        if ($me->role === 'sales_rep' && $target->id !== $me->id) {
            abort(403, 'Reps can only view their own HR record.');
        }
        if ($me->role === 'manager' && $target->id !== $me->id) {
            if (! $target->team_id || $target->team_id !== $me->team_id) {
                abort(403, 'Managers can only view their own team.');
            }
        }

        return $target;
    }

    /** HR data exists for measured roles only (managers + sales). */
    protected function measured(User $target): void
    {
        if (! in_array($target->role, ['manager', 'sales_rep'], true)) {
            abort(404, 'No HR record for this role.');
        }
    }

    protected function month(Request $request): string
    {
        return (string) ($request->query('month') ?: $request->query('period') ?: now()->format('Y-m'));
    }

    public function attendance(Request $request, AttendanceServiceInterface $svc)
    {
        $target = $this->target($request);
        $this->measured($target);

        return $this->ok($svc->monthly($target->id, $this->month($request)) + ['user_id' => $target->id]);
    }

    public function leave(Request $request, AttendanceServiceInterface $svc)
    {
        $target = $this->target($request);
        $this->measured($target);
        $month = $this->month($request);
        $data = $svc->monthly($target->id, $month);

        return $this->ok([
            'user_id' => $target->id,
            'month' => $month,
            'balances' => $data['leave'] ?? [],
            'leave_days' => array_values(array_filter(
                $data['days'] ?? [],
                fn ($d) => ($d['status'] ?? null) === 'leave'
            )),
            'mock' => true,
        ]);
    }

    public function performance(Request $request, PerformanceServiceInterface $svc)
    {
        $target = $this->target($request);
        $this->measured($target);

        return $this->ok($svc->score($target->id, $this->month($request)) + ['user_id' => $target->id]);
    }

    public function directory(Request $request)
    {
        $me = $request->user();
        // Demo/second-factor placeholder account stays out of workforce views.
        $q = User::with('team:id,name')->whereIn('role', ['manager', 'sales_rep'])
            ->where('email', '!=', 'otp.demo@primepower.ph');
        if ($me->role === 'sales_rep') {
            // Reps see teammates (names for transfer pickers); HR detail stays self-only.
            $q->where(function ($w) use ($me) {
                $w->whereKey($me->id);
                if ($me->team_id) {
                    $w->orWhere('team_id', $me->team_id);
                }
            });
        } elseif ($me->role === 'manager') {
            $q->where(function ($w) use ($me) {
                $w->whereKey($me->id);
                if ($me->team_id) {
                    $w->orWhere('team_id', $me->team_id);
                }
            });
        }
        if ($request->query('team_id')) {
            $teamFilter = (int) $request->query('team_id');
            if ($me->role === 'manager' && $me->team_id && $teamFilter !== (int) $me->team_id) {
                abort(403, 'Managers can only view their own team.');
            }
            $q->where('team_id', $teamFilter);
        }
        if ($request->query('q')) {
            $term = $request->query('q');
            $op = $q->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $q->where(fn ($w) => $w->where('name', $op, "%{$term}%")->orWhere('email', $op, "%{$term}%"));
        }
        $rows = $q->orderBy('name')->paginate(min(100, (int) $request->query('per_page', 25)));

        return $this->paginated($rows->through(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'team_id' => $u->team_id,
            'team_name' => $u->team?->name,
            'is_active' => (bool) $u->is_active,
            'last_login_at' => $u->last_login_at,
        ]));
    }
}
