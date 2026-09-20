<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/** JWT baseline auth. OTP second factor lands in Agent H stream (v2); v1 returns tokens directly. */
class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request)
    {
        $user = \App\Models\User::where('email', $request->email)->where('is_active', true)->first();
        if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return $this->fail('Invalid credentials.', 401);
        }
        $token = JWTAuth::fromUser($user);
        $refresh = JWTAuth::claims(['refresh' => true])->fromUser($user);
        $user->update(['last_login_at' => now()]);
        $user->audit('login', $user->id, ['ip' => $request->ip()]);
        try {
            \App\Models\UserSession::create([
                'user_id' => $user->id,
                'jti' => JWTAuth::setToken($token)->getPayload()->get('jti', \Illuminate\Support\Str::uuid()->toString()),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_activity_at' => now(),
            ]);
        } catch (\Throwable) {
            // session tracking must never break login
        }

        return $this->ok([
            'access_token' => $token,
            'refresh_token' => $refresh,
            'user' => $user->only(['id', 'name', 'email', 'role', 'team_id']),
        ], 'Logged in.');
    }

    public function refresh(Request $request)
    {
        try {
            $new = JWTAuth::parseToken()->refresh();
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
}
