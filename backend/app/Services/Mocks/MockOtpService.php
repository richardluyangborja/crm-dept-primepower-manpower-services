<?php

namespace App\Services\Mocks;

use App\Exceptions\OtpLockedException;
use App\Models\AuditLog;
use App\Models\Otp;
use App\Services\Contracts\OtpServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * OTP service (specs/16). Step 8: full enforcement — hashed codes, 5-min TTL,
 * single-use, max-attempt lockout with 15-min cooldown, audit trail.
 * Mock transport: deterministic code + log line (SMTP later, never paid SMS).
 */
class MockOtpService implements OtpServiceInterface
{
    public static function lockKey(int $userId): string
    {
        return "otp:lock:{$userId}";
    }

    public function send(int $userId, string $purpose = 'login'): array
    {
        if ($this->lockedOut($userId)) {
            throw new OtpLockedException('Too many attempts. Try again in 15 minutes.');
        }
        $code = config('otp.mock_code', '123456');
        $otp = Otp::create([
            'user_id' => $userId,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('otp.ttl', 5)),
        ]);
        Log::info('[mock] otp issued', ['user' => $userId, 'purpose' => $purpose, 'id' => $otp->id]);
        $this->audit($userId, 'otp_sent', ['purpose' => $purpose, 'otp_id' => $otp->id]);

        return ['challenge_id' => $otp->id, 'expires_in' => 300, 'mock' => true];
    }

    public function verify(int $userId, string $code, string $purpose = 'login'): bool
    {
        if ($this->lockedOut($userId)) {
            throw new OtpLockedException('Too many attempts. Try again in 15 minutes.');
        }
        $otp = Otp::where('user_id', $userId)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest()->first();
        if (! $otp || $otp->expires_at->isPast()) {
            $this->audit($userId, 'otp_expired', ['purpose' => $purpose]);
            return false;
        }
        if ($otp->attempts >= config('otp.max_attempts', 5)) {
            $this->lock($userId, $otp->id, $purpose);
            throw new OtpLockedException('Too many attempts. Try again in 15 minutes.');
        }
        $otp->increment('attempts');
        if (! Hash::check($code, $otp->code_hash)) {
            if ($otp->fresh()->attempts >= config('otp.max_attempts', 5)) {
                $this->lock($userId, $otp->id, $purpose);
                throw new OtpLockedException('Too many attempts. Try again in 15 minutes.');
            }
            return false;
        }
        $otp->update(['consumed_at' => now()]);
        $this->audit($userId, 'otp_verified', ['purpose' => $purpose]);

        return true;
    }

    public function lockedOut(int $userId): bool
    {
        return Cache::has(self::lockKey($userId));
    }

    protected function lock(int $userId, int $otpId, string $purpose): void
    {
        Cache::put(self::lockKey($userId), true, now()->addMinutes(config('otp.lockout_minutes', 15)));
        Otp::whereKey($otpId)->update(['consumed_at' => now()]); // invalidate the code
        $this->audit($userId, 'otp_locked', ['purpose' => $purpose]);
        Log::warning('[mock] otp locked out', ['user' => $userId, 'purpose' => $purpose]);
    }

    protected function audit(int $userId, string $action, array $meta): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId, 'action' => $action,
                'entity' => 'otps', 'entity_id' => null, 'meta' => $meta,
            ]);
        } catch (\Throwable) {
        }
    }
}
