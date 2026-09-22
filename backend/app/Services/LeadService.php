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

    /**
     * Convert lead → client (+ optional opening opportunity). Idempotent: 409 if already converted.
     *
     * @return array{client: Client, opportunity: ?Opportunity}
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function convertToClient(Lead $lead, array $input, int $actorId): array
    {
        if ($lead->status === 'converted' || $lead->converted_client_id) {
            abort(409, 'Lead already converted.');
        }

        return DB::transaction(function () use ($lead, $input, $actorId) {
            $c = $input['client'] ?? [];
            $client = Client::create([
                'owner_id' => $lead->owner_id,
                'name' => $c['name'] ?? $lead->company_name,
                'industry' => $c['industry'] ?? null,
                'address_city' => $c['address_city'] ?? null,
                'address_province' => $c['address_province'] ?? null,
                'contact_email' => $lead->contact_email,
                'contact_phone' => $lead->contact_phone,
                'status' => 'prospect',
                'source' => $lead->source,
                'created_from_lead_id' => $lead->id,
            ]);
            $client->contacts()->create([
                'full_name' => $lead->contact_name,
                'email' => $lead->contact_email,
                'phone' => $lead->contact_phone,
                'is_primary' => true,
            ]);

            $opportunity = null;
            if (! empty($input['create_opportunity'])) {
                $opportunity = Opportunity::create([
                    'client_id' => $client->id,
                    'owner_id' => $lead->owner_id,
                    'title' => $input['opportunity_title'] ?? "Opening — {$client->name}",
                    'stage' => 'new',
                    'value_centavos' => $input['opportunity_value_centavos'] ?? 0,
                    'probability' => Opportunity::STAGE_PROBABILITY['new'],
                ]);
                $opportunity->audit('created', $actorId, ['via' => 'lead_convert']);
            }

            $lead->update(['status' => 'converted', 'converted_client_id' => $client->id]);
            $client->audit('created', $actorId, ['via' => 'lead_convert', 'lead_id' => $lead->id]);
            $lead->audit('converted', $actorId, ['client_id' => $client->id]);

            return ['client' => $client, 'opportunity' => $opportunity];
        });
    }
}
