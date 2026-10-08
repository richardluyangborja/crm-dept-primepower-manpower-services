<?php

namespace App\Services\Mocks;

use App\Exceptions\OtpCooldownException;
use App\Exceptions\OtpLockedException;
use App\Mail\OtpCodeMail;
use App\Models\AuditLog;
use App\Models\Otp;
use App\Models\User;
use App\Services\Contracts\OtpServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OTP service (specs/16). Step 8: full enforcement — hashed codes, 5-min TTL,
 * single-use, max-attempt lockout with 15-min cooldown, audit trail.
 * Transports: `mock` (fixed code + log line, for tests) and `smtp`
 * (random code emailed via Gmail SMTP — the only supported channel).
 */
class MockOtpService implements OtpServiceInterface
{
    public static function lockKey(int $userId): string
    {
        return "otp:lock:{$userId}";
    }

    public static function cooldownKey(int $userId, string $purpose): string
    {
        return "otp:cooldown:{$userId}:{$purpose}";
    }

    public function send(int $userId, string $purpose = 'login'): array
    {
        if ($this->lockedOut($userId)) {
            throw new OtpLockedException('Too many attempts. Try again in 15 minutes.');
        }
        if (($wait = $this->resendAfter($userId, $purpose)) > 0) {
            throw new OtpCooldownException($wait);
        }
        $ttl = (int) config('otp.ttl', 5);
        $user = User::findOrFail($userId);
        $smtp = config('otp.mode') === 'smtp';
        $code = $smtp ? (string) random_int(100000, 999999) : config('otp.mock_code', '123456');
        $otp = Otp::create([
            'user_id' => $userId,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);
        if ($smtp) {
            try {
                Mail::to($user->email)->send(new OtpCodeMail($user->name, $code, $purpose, $ttl));
            } catch (\Throwable $e) {
                $otp->delete(); // never leave a code the user can't receive
                Log::error('[otp] email failed', ['user' => $userId, 'purpose' => $purpose, 'error' => $e->getMessage()]);
                throw new \RuntimeException('Could not send the code by email — check the address or try again.');
            }
        }
        Log::info('[otp] issued', ['user' => $userId, 'purpose' => $purpose, 'id' => $otp->id, 'channel' => $smtp ? 'email' : 'log']);
        $this->audit($userId, 'otp_sent', ['purpose' => $purpose, 'otp_id' => $otp->id, 'channel' => $smtp ? 'email' : 'log']);
        $cooldown = (int) config('otp.resend_cooldown', 60);
        Cache::put(self::cooldownKey($userId, $purpose), true, now()->addSeconds($cooldown));
        Cache::put(self::cooldownKey($userId, $purpose).':until', time() + $cooldown, now()->addSeconds($cooldown + 5));

        return [
            'challenge_id' => $otp->id,
            'expires_in' => $ttl * 60,
            'channel' => $smtp ? 'email' : 'log',
            'sent_to' => self::maskEmail($user->email),
        ];
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

    public function attemptsLeft(int $userId, string $purpose = 'login'): ?int
    {
        $otp = Otp::where('user_id', $userId)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest()->first();
        if (! $otp || $otp->expires_at->isPast()) {
            return null;
        }

        return max(0, (int) config('otp.max_attempts', 5) - (int) $otp->attempts);
    }

    public function resendAfter(int $userId, string $purpose = 'login'): int
    {
        $ttl = Cache::get(self::cooldownKey($userId, $purpose));
        if ($ttl === null) {
            return 0;
        }
        // File/database cache drivers don't expose TTL; track the deadline instead.
        $until = Cache::get(self::cooldownKey($userId, $purpose).':until');
        if ($until === null) {
            return 0;
        }

        return max(0, (int) $until - time());
    }

    /** r•••@gmail.com style — proves where the code went without leaking it. */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return 'your email';
        }

        return substr($email, 0, 1).'•••'.substr($email, $at);
    }

    protected function lock(int $userId, int $otpId, string $purpose): void
    {
        Cache::put(self::lockKey($userId), true, now()->addMinutes(config('otp.lockout_minutes', 15)));
        Otp::whereKey($otpId)->update(['consumed_at' => now()]); // invalidate the code
        $this->audit($userId, 'otp_locked', ['purpose' => $purpose]);
        Log::warning('[otp] locked out', ['user' => $userId, 'purpose' => $purpose]);
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
