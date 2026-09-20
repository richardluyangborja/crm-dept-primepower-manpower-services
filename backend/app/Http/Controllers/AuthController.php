<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\IssuesAuthTokens;
use App\Http\Requests\LoginRequest;
use App\Services\Contracts\OtpServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/** JWT auth with OTP second factor (specs/16): gated roles + otp_enabled users get a challenge first. */
class AuthController extends Controller
{
    use ApiResponse, IssuesAuthTokens;

    public static function otpRequiredFor(\App\Models\User $user): bool
    {
        if ($user->otp_enabled) return true;

        return in_array($user->role, config('otp.required_roles', ['superadmin', 'admin']), true);
    }

    public function login(LoginRequest $request, OtpServiceInterface $otp)
    {
        $user = \App\Models\User::where('email', $request->email)->where('is_active', true)->first();
        if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return $this->fail('Invalid credentials.', 401);
        }
        if (self::otpRequiredFor($user)) {
            try {
                $challenge = $otp->send($user->id, 'login');
            } catch (\App\Exceptions\OtpLockedException $e) {
                return $this->fail($e->getMessage(), 429);
            }

            return $this->ok([
                'otp_required' => true,
                'challenge_id' => $challenge['challenge_id'],
                'expires_in' => $challenge['expires_in'],
            ], 'Verification code sent — enter the 6-digit code.');
        }

        return $this->ok($this->issueTokens($user, $request), 'Logged in.');
    }

    public function refresh(Request $request)
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            // Exact link via sid (real logins); else latest row (legacy tokens).
            // Consult expired rows too: an idle-expired session must stay dead.
            $session = \App\Models\UserSession::where('jti', $payload->get('sid'))->first()
                ?? \App\Models\UserSession::where('user_id', $payload->get('sub'))
                    ->latest('last_activity_at')->first();
            if ($session && ($session->expired_at || $this->idleExpired($session) || $this->absoluteExpired($session))) {
                $session->update(['expired_at' => $session->expired_at ?? now()]);
                return response()->json(['message' => 'Session expired after 5 minutes of inactivity — please log in again.', 'code' => 'session_expired'], 401);
            }
            $new = JWTAuth::refresh();
            if ($session) {
                // Rotate to the fresh access jti so the 12h absolute anchor survives refresh.
                $newJti = JWTAuth::setToken($new)->getPayload()->get('jti');
                $session->update(['jti' => $newJti ?? $session->jti, 'last_activity_at' => now()]);
            }
        } catch (\Throwable) {
            return $this->fail('Session expired. Please log in again.', 401);
        }

        return $this->ok(['access_token' => $new], 'Token refreshed.');
    }

    public function logout()
    {
        try {
            $jti = JWTAuth::parseToken()->getPayload()->get('jti');
            JWTAuth::invalidate();
            if ($jti) {
                \App\Models\UserSession::where('jti', $jti)->update(['expired_at' => now()]);
            }
        } catch (\Throwable) {
        }

        return $this->ok(null, 'Logged out.');
    }

    public function me()
    {
        return $this->ok(auth('api')->user());
    }

    protected function idleExpired(\App\Models\UserSession $session): bool
    {
        return $session->last_activity_at && $session->last_activity_at->lt(now()->subSeconds(config('otp.session_idle_timeout', 300)));
    }

    protected function absoluteExpired(\App\Models\UserSession $session): bool
    {
        return $session->created_at && $session->created_at->lt(now()->subSeconds(config('otp.session_absolute_timeout', 43200)));
    }
}
