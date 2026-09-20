<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpLockedException;
use App\Http\Controllers\Concerns\IssuesAuthTokens;
use App\Services\Contracts\OtpServiceInterface;
use App\Services\StepUpService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * OTP endpoints (specs/16). Login verify is PUBLIC (email+code, throttled);
 * step-up verify requires auth and returns a single-use grant token.
 */
class OtpController extends Controller
{
    use ApiResponse, IssuesAuthTokens;

    public function send(Request $request, OtpServiceInterface $otp)
    {
        $request->validate(['purpose' => 'sometimes|string|in:login,step_up']);
        $purpose = $request->input('purpose', 'login');
        // Cooldown is per-user: check before anything else (specs/16).
        $callerId = auth('api')->id();
        if ($callerId && $otp->lockedOut($callerId)) {
            return $this->fail('Too many attempts. Try again in 15 minutes.', 429);
        }
        $userId = $purpose === 'step_up' ? $callerId : null;
        if (! $userId) {
            return $this->fail('Login OTP is issued automatically on sign-in.', 422);
        }
        try {
            $result = $otp->send($userId, $purpose);
        } catch (OtpLockedException $e) {
            return $this->fail($e->getMessage(), 429);
        }

        return $this->created($result, 'Code sent — it expires in 5 minutes (mock: check logs).');
    }

    public function verify(Request $request, OtpServiceInterface $otp, StepUpService $stepUp)
    {
        $request->validate([
            'email' => ['sometimes', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'purpose' => ['sometimes', 'string', 'in:login,step_up'],
        ]);
        $purpose = $request->input('purpose', 'login');

        if ($purpose === 'step_up') {
            $user = auth('api')->user();
            if (! $user) return $this->fail('Unauthenticated.', 401);
            try {
                $ok = $otp->verify($user->id, $request->code, 'step_up');
            } catch (OtpLockedException $e) {
                return $this->fail($e->getMessage(), 429);
            }
            if (! $ok) return $this->fail('Invalid or expired code.', 410);
            $grant = $stepUp->grant($user->id);

            return $this->ok(['step_up_token' => $grant, 'expires_in' => 300], 'Verified — token valid 5 minutes, single use.');
        }

        // Login second factor: identify by email (public, throttled at route).
        if (! $request->email) {
            return $this->fail('Email is required with the code.', 422);
        }
        $user = \App\Models\User::where('email', $request->email)->where('is_active', true)->first();
        if (! $user) return $this->fail('Invalid or expired code.', 410);
        try {
            $ok = $otp->verify($user->id, $request->code, 'login');
        } catch (OtpLockedException $e) {
            return $this->fail($e->getMessage(), 429);
        }
        if (! $ok) return $this->fail('Invalid or expired code.', 410);

        return $this->ok($this->issueTokens($user, $request), 'Verified — logged in.');
    }
}
