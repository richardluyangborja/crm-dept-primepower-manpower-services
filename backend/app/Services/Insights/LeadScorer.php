<?php

namespace App\Services\Insights;

use App\Models\Lead;

/** v1 rule-based lead score (specs/04 + 15). Deterministic, no ML. */
class LeadScorer
{
    public static function score(Lead $lead): int
    {
        $score = 0;
        if ($lead->contact_email && str_ends_with($lead->contact_email, '.ph')) {
            $score += 20;
        } elseif ($lead->contact_email) {
            $score += 10;
        }
        if ($lead->address_city ?? false) {
            $score += 15;
        }
        if ($lead->contact_phone && preg_match('/^\+63\d{10}$/', $lead->contact_phone)) {
            $score += 25;
        }
        if ($lead->notes) {
            $score += 10;
        }
        if (in_array($lead->status, ['qualified', 'converted'], true)) {
            $score += 30;
        } elseif ($lead->status === 'contacted') {
            $score += 15;
        }

        return min(100, $score);
    }
}
