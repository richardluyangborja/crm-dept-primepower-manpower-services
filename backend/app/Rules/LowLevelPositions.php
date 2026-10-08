<?php

namespace App\Rules;

use App\Models\Lead;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Agency guard: deployed `positions` must be low-level roles only.
 * Accepts comma-separated values, each optionally prefixed with a
 * headcount ("60 packers", "guards, janitors"). Rejects anything
 * outside Lead::LOW_LEVEL_POSITIONS (heads, managers, directors…).
 */
class LowLevelPositions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            $fail('Positions must be a comma-separated list of deployable roles.');

            return;
        }
        $allowed = array_map('mb_strtolower', Lead::LOW_LEVEL_POSITIONS);
        $parts = array_filter(array_map('trim', explode(',', $value)));
        if ($parts === []) {
            return;
        }
        foreach ($parts as $part) {
            // Strip an optional leading headcount ("60 packers" → "packers").
            $name = trim((string) preg_replace('/^\d+\s+/', '', $part));
            if ($name === '') {
                continue;
            }
            $lower = mb_strtolower($name);
            // Direct allowlist hit (singular/plural tolerant).
            if (in_array($lower, $allowed, true) || in_array($lower.'s', $allowed, true) || in_array((string) preg_replace('/s$/', '', $lower), $allowed, true)) {
                continue;
            }
            // Explicit ban on supervisory titles even if phrased oddly.
            if (preg_match('/\b(manager|head|director|supervisor|officer|executive|chief|lead\b.*manager|general\s+manager)\b/i', $name)) {
                $fail("“{$part}” is not deployable — this agency only deploys rank-and-file roles (no heads/managers). Pick from: ".implode(', ', Lead::LOW_LEVEL_POSITIONS).'.');

                return;
            }
            $fail("“{$part}” is not a supported deployable role. Pick from: ".implode(', ', Lead::LOW_LEVEL_POSITIONS).'.');

            return;
        }
    }
}
