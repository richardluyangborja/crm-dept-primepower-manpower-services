<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\Insights\LeadScorer;
use Illuminate\Support\Facades\DB;

/** Lead domain logic (specs/04). Controllers stay thin; agents reuse these. */
class LeadService
{
    /** Duplicate hint (warn, don't block): same email or phone on another lead/client. */
    public function findDuplicate(array $attrs): ?array
    {
        foreach (['contact_email' => Lead::class, 'contact_phone' => Lead::class] as $field => $model) {
            if (empty($attrs[$field])) {
                continue;
            }
            $hit = $model::where($field, $attrs[$field])->first(['id']);
            if ($hit) {
                return ['type' => 'lead', 'id' => $model::encodeId($hit->id), 'field' => $field];
            }
        }
        foreach (['contact_email', 'contact_phone'] as $field) {
            if (empty($attrs[$field])) {
                continue;
            }
            $hit = Client::where($field, $attrs[$field])->first(['id']);
            if ($hit) {
                return ['type' => 'client', 'id' => Client::encodeId($hit->id), 'field' => $field];
            }
        }

        return null;
    }

    public function score(Lead $lead): int
    {
        $score = LeadScorer::score($lead);
        $lead->updateQuietly(['score' => $score]);

        return $score;
    }
}
