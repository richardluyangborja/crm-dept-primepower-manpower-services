<?php

namespace App\Http\Controllers;

use App\Services\Contracts\AiServiceInterface;
use App\Services\Insights\ChurnRisk;
use App\Services\Insights\NextBestAction;
use App\Services\Insights\WinProbability;
use App\Traits\ApiResponse;

/** Rules-first insights; AiService mock attached for shape-compat (specs/15). */
class InsightController extends Controller
{
    use ApiResponse;

    public function client(int $id)
    {
        $user = auth('api')->user();
        $client = \App\Models\Client::visibleTo($user)->findOrFail($id);

        return $this->ok(ChurnRisk::assess($client) + [
            'nba' => NextBestAction::forClient($client),
            'ai_preview' => true,
        ]);
    }

    public function opportunity(int $id, AiServiceInterface $ai)
    {
        $user = auth('api')->user();
        $opp = \App\Models\Opportunity::visibleTo($user)->findOrFail($id);
        $rules = WinProbability::forOpp($opp);

        return $this->ok($rules + ['ai_preview' => true, 'ai' => $ai->winProbability($id)]);
    }
}
