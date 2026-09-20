<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/** Shared JWT issuance so password-login and OTP-verify behave identically. */
trait IssuesAuthTokens
{
    /** @return array{access_token: string, refresh_token: string, user: array} */
    protected function issueTokens(User $user, Request $request): array
    {
        $token = JWTAuth::fromUser($user);
        // Link refresh → session: the access jti rides along as `sid` so idle/
        // absolute timeouts also gate token refresh (specs/16).
        $jti = JWTAuth::setToken($token)->getPayload()->get('jti', Str::uuid()->toString());
        $refresh = JWTAuth::claims(['refresh' => true, 'sid' => $jti])->fromUser($user);
        $user->update(['last_login_at' => now()]);
        $user->audit('login', $user->id, ['ip' => $request->ip()]);
        try {
            UserSession::create([
                'user_id' => $user->id,
                'jti' => $jti,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_activity_at' => now(),
            ]);
        } catch (\Throwable) {
            // session tracking must never break login
        }

        return [
            'access_token' => $token,
            'refresh_token' => $refresh,
            'user' => $user->only(['id', 'name', 'email', 'role', 'team_id']),
        ];
    }
}
