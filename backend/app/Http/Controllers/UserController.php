<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\PreferencesRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSession;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Step 6 (specs/09): Users & Access, preferences, password, sessions, login history. */
class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $user = $request->user();
        $q = User::with('team');
        if ($user->role !== 'superadmin') {
            $q->where('role', '!=', 'superadmin'); // the top account is seeded, never listed
        }
        if ($user->role === 'manager') {
            $q->where('team_id', $user->team_id); // read-only team view; writes blocked by policy
        }
        if ($request->query('team_id')) $q->where('team_id', $request->query('team_id'));
        if ($request->query('role')) $q->where('role', $request->query('role'));
        if ($request->query('q')) {
            $term = $request->query('q');
            $op = $q->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $q->where(fn ($qq) => $qq->where('name', $op, "%{$term}%")->orWhere('email', $op, "%{$term}%"));
        }

        return $this->paginated(UserResource::collection($q->orderBy('name')->paginate(min(100, (int) $request->query('per_page', 25)))));
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);
        $user = User::create($request->validated());
        $user->audit('invited', $request->user()->id, ['email' => $user->email, 'role' => $user->role]);

        return $this->created(new UserResource($user->load('team')), 'Account created — share the temporary password securely.');
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return $this->ok(new UserResource($user->load('team')));
    }

    public function update(UpdateUserRequest $request, User $user, \App\Services\StepUpService $stepUp)
    {
        $this->authorize('update', $user);
        if ($user->role === 'superadmin') {
            return $this->fail('The superadmin account is seed-managed and cannot be changed.', 422);
        }
        $data = $request->validated();
        if (array_key_exists('role', $data) && $data['role'] !== $user->role) {
            // Privilege change = step-up (specs/16): fresh OTP grant required.
            if (! $stepUp->consume($request->header('X-StepUp-Token'), $request->user()->id)) {
                return response()->json(['message' => 'Role change needs a fresh verification code.', 'otp_required' => true], 428);
            }
            if ($user->id === $request->user()->id) {
                return $this->fail('You cannot change your own role.', 422);
            }
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            if ($user->id === $request->user()->id) return $this->fail('You cannot deactivate your own account.', 422);
            if ($user->role === 'superadmin' && User::where('role', 'superadmin')->where('is_active', true)->count() <= 1) {
                return $this->fail('Cannot deactivate the last active superadmin.', 422);
            }
        }
        $user->update($data);
        $user->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new UserResource($user->refresh()->load('team')), 'Account updated.');
    }

    public function deactivate(User $user)
    {
        $this->authorize('deactivate', User::class);
        if ($user->role === 'superadmin') {
            return $this->fail('The superadmin account is seed-managed and cannot be deactivated.', 422);
        }
        if ($user->id === auth('api')->id()) return $this->fail('You cannot deactivate your own account.', 422);
        $user->update(['is_active' => false]);
        $user->audit('deactivated', auth('api')->id(), []);

        return $this->ok(new UserResource($user->refresh()), 'Account deactivated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('resetPassword', User::class);
        if ($user->role === 'superadmin') {
            return $this->fail('The superadmin account is seed-managed and cannot be changed.', 422);
        }
        $request->validate(['password' => ['required', 'string', 'min:10']]);
        $user->update(['password' => $request->input('password')]);
        $user->audit('password_reset', $request->user()->id, []);

        return $this->ok(null, 'Password reset — share the new temporary password securely.');
    }

    public function preferences(Request $request)
    {
        return $this->ok($request->user()->preferences ?? $this->defaults());
    }

    public function updatePreferences(PreferencesRequest $request)
    {
        $user = $request->user();
        $merged = array_merge($this->defaults(), $user->preferences ?? [], $request->validated());
        $user->update(['preferences' => $merged]);

        return $this->ok($merged, 'Preferences saved.');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->fail('Current password is incorrect.', 422);
        }
        $user->update(['password' => $request->input('password')]);
        $user->audit('password_changed', $user->id, []);

        return $this->ok(null, 'Password changed.');
    }

    public function sessions(Request $request)
    {
        $user = $request->user();
        $q = UserSession::query()->whereNull('expired_at');
        if (! in_array($user->role, ['superadmin', 'admin'], true)) {
            $q->where('user_id', $user->id);
        } elseif ($request->query('user_id')) {
            $q->where('user_id', $request->query('user_id'));
        }

        return $this->paginated($q->latest('last_activity_at')->paginate(min(100, (int) $request->query('per_page', 25))));
    }

    public function revokeSession(UserSession $userSession)
    {
        $user = auth('api')->user();
        if (! in_array($user->role, ['superadmin', 'admin'], true) && $userSession->user_id !== $user->id) {
            return $this->fail('Forbidden. Ask your manager for access.', 403);
        }
        $userSession->update(['expired_at' => now()]);

        return $this->ok(null, 'Session revoked. Full token invalidation lands with OTP enforcement (specs/16).');
    }

    public function logins(Request $request)
    {
        $user = $request->user();
        $q = AuditLog::where('action', 'login');
        if (! in_array($user->role, ['superadmin', 'admin'], true)) {
            $q->where('user_id', $user->id);
        } elseif ($request->query('user_id')) {
            $q->where('user_id', $request->query('user_id'));
        }

        return $this->paginated($q->orderByDesc('id')->paginate(min(100, (int) $request->query('per_page', 25))));
    }

    protected function defaults(): array
    {
        return [
            'theme' => 'system',
            'sync_system' => true,
            'notifications' => ['reminder_due' => true, 'overdue' => true, 'escalation' => true, 'survey_response' => true, 'assignment' => true],
            'quiet_hours_start' => null,
            'quiet_hours_end' => null,
        ];
    }
}
