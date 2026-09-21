<?php

namespace App\Services\Insights;

/**
 * Front-office fulfillment rollup (specs/04): required vs deployed vs remaining
 * plus a plain-language status. Read-only — Client Management owns execution.
 */
class ClientFulfillment
{
    public static function forClient(\App\Models\Client $client): array
    {
        $workforce = app(\App\Services\Contracts\WorkforceServiceInterface::class);
        $required = (int) \App\Models\Contract::where('client_id', $client->id)
            ->where('status', 'active')->sum('headcount');
        if ($required === 0) {
            $required = (int) \App\Models\JobOrder::where('client_id', $client->id)
                ->whereNotIn('status', ['billed'])->sum('headcount');
        }
        $hc = $workforce->headcountByClient($client->id);
        $deployed = (int) ($hc['deployed'] ?? 0);
        $remaining = max(0, $required - $deployed);
        $pct = $required > 0 ? (int) round($deployed / $required * 100) : null;

        $jobs = \App\Models\JobOrder::where('client_id', $client->id)->pluck('status');
        $status = match (true) {
            $required > 0 && $remaining === 0 => 'fully_fulfilled',
            $deployed > 0 || $jobs->contains('deployed') => 'partially_fulfilled',
            $jobs->isEmpty() => 'none',
            $jobs->contains('staffed') => 'processing',
            default => 'open',
        };

        return [
            'required' => $required,
            'deployed' => $deployed,
            'remaining' => $remaining,
            'pct' => $pct,
            'status' => $status,
            'mock' => true,
        ];
    }
}
