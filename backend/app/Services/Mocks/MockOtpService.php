<?php

namespace App\Services\Mocks;

use App\Models\Otp;
use App\Services\Contracts\OtpServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * OTP mock (specs/16). Writes hashed rows; returns deterministic codes in mock mode.
 * V1: issues rows only — enforcement lands in v2.
 */
class MockOtpService implements OtpServiceInterface
{
    public function send(int $userId, string $purpose = 'login'): array
    {
        $code = config('otp.mock_code', '123456');
        $otp = Otp::create([
            'user_id' => $userId,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('otp.ttl', 5)),
        ]);
        Log::info('[mock] otp issued', ['user' => $userId, 'purpose' => $purpose, 'id' => $otp->id]);

        return ['challenge_id' => $otp->id, 'expires_in' => 300, 'mock' => true];
    }

    public function verify(int $userId, string $code, string $purpose = 'login'): bool
    {
        $otp = Otp::where('user_id', $userId)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest()->first();
        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= 5) {
            return false;
        }
        $otp->increment('attempts');
        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }
        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
