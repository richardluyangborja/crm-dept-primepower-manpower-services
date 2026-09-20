<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Step-up grants for sensitive actions (specs/16): random single-use tokens,
 * 5-minute TTL, consumed atomically via Cache::pull.
 */
class StepUpService
{
    public static function grantKey(string $token): string
    {
        return "stepup:{$token}";
    }

    /** @return the grant token to send back in X-StepUp-Token */
    public function grant(int $userId): string
    {
        $token = Str::random(40);
        Cache::put(self::grantKey($token), $userId, now()->addMinutes(5));

        return $token;
    }

    /** Consumes the grant; true only once, for the same user, within TTL. */
    public function consume(?string $token, int $userId): bool
    {
        if (! $token) return false;
        $owner = Cache::pull(self::grantKey($token));

        return $owner !== null && (int) $owner === $userId;
    }
}
