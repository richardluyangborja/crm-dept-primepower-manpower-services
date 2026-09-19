<?php

return [
    'mode' => env('OTP_MODE', 'mock'),   // mock|live (live = v2, specs/16)
    'ttl' => env('OTP_TTL', 5),          // minutes
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
    'mock_code' => env('OTP_MOCK_CODE', '123456'), // mock mode only
    'session_idle_timeout' => env('SESSION_IDLE_TIMEOUT', 300),       // seconds (enforced v2)
    'session_absolute_timeout' => env('SESSION_ABSOLUTE_TIMEOUT', 43200),
];
