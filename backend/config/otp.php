<?php

return [
    'mode' => env('OTP_MODE', 'mock'),   // mock|live (live = v2, specs/16)
    'ttl' => env('OTP_TTL', 5),          // minutes
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
    'mock_code' => env('OTP_MOCK_CODE', '123456'), // mock mode only
    'lockout_minutes' => env('OTP_LOCKOUT_MINUTES', 15),
    // Roles forced through login OTP (specs/16); per-user otp_enabled flag opts anyone else in.
    'required_roles' => array_filter(array_map('trim', explode(',', (string) env('OTP_REQUIRED_ROLES', 'superadmin,admin')))),
    'session_idle_timeout' => env('SESSION_IDLE_TIMEOUT', 300),       // seconds
    'session_absolute_timeout' => env('SESSION_ABSOLUTE_TIMEOUT', 43200),
];
