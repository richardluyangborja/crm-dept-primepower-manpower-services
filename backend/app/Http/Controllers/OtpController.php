<?php

namespace App\Http\Controllers;

use App\Services\Contracts\OtpServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** OTP mock endpoints (specs/16). V1: issues/verifies mock rows only — no token gating. */
class OtpController extends Controller
{
    use ApiResponse;

    public function send(Request $request, OtpServiceInterface $otp)
    {
        $request->validate(['purpose' => 'sometimes|string|in:login,step_up']);
        $result = $otp->send(auth('api')->id(), $request->input('purpose', 'login'));

        return $this->created($result, 'OTP sent (mock — check logs in v1).');
    }

    public function verify(Request $request, OtpServiceInterface $otp)
    {
        $request->validate(['code' => 'required|string|size:6', 'purpose' => 'sometimes|string']);
        $ok = $otp->verify(auth('api')->id(), $request->code, $request->input('purpose', 'login'));
        if (! $ok) {
            return $this->fail('Invalid or expired code.', 410);
        }

        return $this->ok(['verified' => true], 'OTP verified (mock).');
    }
}
