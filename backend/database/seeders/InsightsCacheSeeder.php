<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\InsightCache;
use App\Services\Insights\ChurnRisk;
use Illuminate\Database\Seeder;

/** Step 7 (specs/15 §6): prefill deterministic risk cache so dashboards render offline. */
class InsightsCacheSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Client::all() as $client) {
            $risk = ChurnRisk::assess($client);
            if ($risk['level'] === 'low') continue;
            InsightCache::updateOrCreate(
                ['client_id' => $client->id, 'kind' => 'churn_risk'],
                ['payload' => $risk, 'confidence' => $risk['confidence'], 'generated_at' => now()]
            );
        }
    }
}
