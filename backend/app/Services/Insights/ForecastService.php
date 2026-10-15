<?php

namespace App\Services\Insights;

use App\Models\Opportunity;

/** Weighted forecast: value × probability (specs/05 + 15). */
class ForecastService
{
    public static function weighted(int $valueCentavos, int $probability): int
    {
        return (int) round($valueCentavos * ($probability / 100));
    }

    public static function pipelineTotals(mixed $user): array
    {
        // Single aggregate row — was a full collection load + PHP sums.
        $row = Opportunity::visibleTo($user)
            ->whereNotIn('stage', ['won', 'lost'])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(value_centavos),0) as open, COALESCE(SUM(value_centavos * probability / 100.0),0) as weighted')
            ->first();

        return [
            'open_centavos' => (int) ($row->open ?? 0),
            'weighted_centavos' => (int) round($row->weighted ?? 0),
            'count' => (int) ($row->count ?? 0),
        ];
    }
}
