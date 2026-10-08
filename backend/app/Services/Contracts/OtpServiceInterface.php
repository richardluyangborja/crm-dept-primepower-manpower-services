<?php

namespace App\Services\Contracts;

interface OtpServiceInterface
{
    /** Issue a 6-digit OTP. Mock mode uses the fixed code; smtp mode emails a random one. */
    public function send(int $userId, string $purpose = 'login'): array; // ['challenge_id'=>…, 'expires_in'=>…, 'channel'=>…, 'sent_to'=>…]

    public function verify(int $userId, string $code, string $purpose = 'login'): bool;

    public function lockedOut(int $userId): bool;

    /** Wrong tries left on the latest live code (null when none). Shown so slow typists know where they stand. */
    public function attemptsLeft(int $userId, string $purpose = 'login'): ?int;

    /** Seconds until a resend is allowed (0 = now). */
    public function resendAfter(int $userId, string $purpose = 'login'): int;
}
