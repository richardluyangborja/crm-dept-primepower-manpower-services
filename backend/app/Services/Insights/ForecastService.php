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
        $opps = Opportunity::visibleTo($user)
            ->whereNotIn('stage', ['won', 'lost'])->get();
        $open = $opps->sum('value_centavos');
        $weighted = $opps->sum(fn ($o) => self::weighted($o->value_centavos, $o->probability));

        return ['open_centavos' => $open, 'weighted_centavos' => $weighted, 'count' => $opps->count()];
    }
}
