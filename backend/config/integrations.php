<?php

return [
    // v1 is mock-only across all 9 integrated departments (specs/11).
    // Live implementations land per-service in v2 — no controller changes.
    'mode' => env('INTEGRATIONS_MODE', 'mock'),
];
