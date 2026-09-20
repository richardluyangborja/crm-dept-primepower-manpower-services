<?php

namespace App\Services\Contracts;

interface OtpServiceInterface
{
    /** Issue a 6-digit OTP (5-min TTL). Mock mode returns code '123456' pattern fixtures. */
    public function send(int $userId, string $purpose = 'login'): array; // ['challenge_id'=>…]

    public function verify(int $userId, string $code, string $purpose = 'login'): bool;

    public function lockedOut(int $userId): bool;
}
