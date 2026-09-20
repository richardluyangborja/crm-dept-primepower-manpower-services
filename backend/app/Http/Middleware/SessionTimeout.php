<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 5-minute idle + 12-hour absolute session enforcement (specs/16).
 * Runs after auth:api. Missing row → lazily created (covers tokens minted
 * outside HTTP login, e.g. tests); every later request is then gated.
 */
class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();
        try {
            $jti = JWTAuth::parseToken()->getPayload()->get('jti');
        } catch (\Throwable) {
            return $next($request);
        }

        $session = $jti ? UserSession::where('jti', $jti)->first() : null;
        if (! $session) {
            try {
                UserSession::create([
                    'user_id' => $user->id,
                    'jti' => $jti ?? (string) \Illuminate\Support\Str::uuid(),
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'last_activity_at' => now(),
                ]);
            } catch (\Throwable) {
            }

            return $next($request);
        }

        $idle = (int) config('otp.session_idle_timeout', 300);
        $absolute = (int) config('otp.session_absolute_timeout', 43200);
        $expired = $session->expired_at
            || ($session->last_activity_at && $session->last_activity_at->lt(now()->subSeconds($idle)))
            || ($session->created_at && $session->created_at->lt(now()->subSeconds($absolute)));
        if ($expired) {
            $session->update(['expired_at' => $session->expired_at ?? now()]);

            return response()->json([
                'message' => 'Session expired after 5 minutes of inactivity — please log in again.',
                'code' => 'session_expired',
            ], 401);
        }
        $session->update(['last_activity_at' => now()]);

        return $next($request);
    }
}
