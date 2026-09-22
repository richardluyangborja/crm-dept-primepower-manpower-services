<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\SurveyResponse;
use App\Services\Insights\ChurnRisk;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Per-client BI drilldown (specs/05 hub). Manager+ only, like Reports. */
class BiController extends Controller
{
    use ApiResponse;

    public function clientBreakdown(Request $request)
    {
        if (! in_array($request->user()->role, ['manager', 'admin', 'superadmin'], true)) {
            return $this->fail('BI drilldown is available to managers and above.', 403);
        }
        $clients = Client::visibleTo($request->user())->with('owner:id,name')->orderBy('name')->get();

        $rows = $clients->map(function ($client) {
            $opps = Opportunity::where('client_id', $client->id)->get();
            $open = $opps->whereNotIn('stage', ['won', 'lost']);
            $won = $opps->where('stage', 'won');
            $surveys = \App\Models\Survey::where('client_id', $client->id)->get();
            $scores = SurveyResponse::whereIn('survey_id', $surveys->pluck('id'))->pluck('score');
            $outstanding = Invoice::where('client_id', $client->id)->where('status', '!=', 'paid')->sum('balance_centavos');
            $risk = ChurnRisk::assess($client);

            return [
                'client_id' => \App\Models\Client::encodeId($client->id),
                'client_name' => $client->name,
                'owner_name' => $client->owner?->name,
                'deals' => $opps->count(),
                'open_value_centavos' => (int) $open->sum('value_centavos'),
                'won_value_centavos' => (int) $won->sum('value_centavos'),
                'surveys' => $surveys->count(),
                'nps_avg' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'outstanding_centavos' => (int) $outstanding,
                'risk' => $risk['level'],
                'risk_drivers' => $risk['drivers'],
                'mock' => true,
            ];
        })->values()->all();

        return $this->ok(['data' => $rows, 'meta' => ['clients' => count($rows), 'mock' => true]]);
    }
}
