<?php

namespace App\Services\Insights;

use App\Models\Lead;

/** v1 rule-based lead score (specs/04 + 15). Deterministic, no ML. */
class LeadScorer
{
    public static function score(Lead $lead): int
    {
        $score = 0;
        // Any syntactically valid email scores — agency deals with
        // gmail/yahoo/corporate alike; deliverability is not checked in v1.
        if ($lead->contact_email && filter_var($lead->contact_email, FILTER_VALIDATE_EMAIL)) {
            $score += 20;
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
        if ($lead->headcount_needed) {
            $score += 10; // buyer signal: they know what they need
        }
        if (in_array($lead->status, ['qualified', 'converted'], true)) {
            $score += 30;
        } elseif ($lead->status === 'contacted') {
            $score += 15;
        }

        return min(100, $score);
    }
}
