<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use Illuminate\Database\Seeder;

class CrmSatisfactionSeeder extends Seeder
{
    /**
     * Year-round satisfaction coverage: every client gets a monthly NPS
     * pulse + quarterly CSAT across the trailing 12 months, so the
     * satisfaction line chart and the management report read properly.
     * Static, deterministic, no Faker. Idempotent via token firstOrCreate.
     */
    public function run(): void
    {
        $nps = SurveyTemplate::firstOrCreate(
            ['name' => 'Monthly Client Pulse'],
            ['type' => 'nps', 'questions' => [['q' => 'How likely are you to recommend Primepower?', 'scale' => 10]], 'is_active' => true]
        );
        $csat = SurveyTemplate::firstOrCreate(
            ['name' => 'Deployment CSAT'],
            ['type' => 'csat', 'questions' => [['q' => 'How satisfied are you with the deployment?', 'scale' => 5]], 'is_active' => true]
        );

        $commentsHigh = ['Mabilis ang deployment, salamat!', 'Excellent guards, very professional!', 'Maayos ang coordination.', 'Ok ang serbisyo, salamat.', 'Relievers arrive on time. Good account handling.'];
        $commentsMid = ['Okay naman, pero need reliever pag Sunday.', 'Maayos, though billing SOA was late once.', 'Deployment was fast; uniforms took a while.', 'Satisfied overall, minor shifting gaps.'];
        $commentsLow = ['Mabagal ang response sa quotation.', 'Paki-follow up ang billing, thanks.', 'Reliever coverage on Sundays needs work.', 'Several no-shows last month, please improve.'];

        $clients = Client::orderBy('id')->get();
        foreach ($clients as $idx => $client) {
            $arc = $idx % 5;
            foreach (range(11, 0) as $m) {
                $month = now()->subMonths($m);
                $ym = $month->format('Y-m');
                $day = 8 + (($idx * 5 + $m * 3) % 18); // answered between the 8th–25th
                $answered = $month->copy()->startOfMonth()->addDays(min($day, 25))->setTime(10 + ($idx % 7), 15);

                $score = $this->npsScore($arc, $m, $idx);
                $isCurrentMonth = $m === 0;
                // Current month: half the clients still have the pulse in flight.
                $pending = $isCurrentMonth && ($idx % 2 === 0);

                $survey = Survey::firstOrCreate(
                    ['token' => "seeded-sat-{$client->id}-{$ym}-nps"],
                    [
                        'template_id' => $nps->id, 'client_id' => $client->id,
                        'sent_by' => $client->owner_id, 'channel' => $m % 2 ? 'link' : 'email_mock',
                        'status' => $pending ? 'sent' : 'responded',
                        'due_at' => $month->copy()->endOfMonth(),
                        'created_at' => $month->copy()->startOfMonth()->addDays(2), 'updated_at' => $pending ? now() : $answered,
                    ]
                );
                if (! $pending) {
                    $pool = $score >= 9 ? $commentsHigh : ($score >= 7 ? $commentsMid : $commentsLow);
                    $hasComment = (($idx + $m) % 5) < 3;
                    SurveyResponse::firstOrCreate(
                        ['survey_id' => $survey->id],
                        [
                            'score' => $score,
                            'comment' => $hasComment ? $pool[($idx + $m) % count($pool)] : null,
                            'responded_at' => $answered,
                        ]
                    );
                }

                // Quarterly CSAT alongside the pulse.
                if ($m % 3 === 2 && ! $pending) {
                    $csatSurvey = Survey::firstOrCreate(
                        ['token' => "seeded-sat-{$client->id}-{$ym}-csat"],
                        [
                            'template_id' => $csat->id, 'client_id' => $client->id,
                            'sent_by' => $client->owner_id, 'channel' => 'link',
                            'status' => 'responded',
                            'due_at' => $month->copy()->endOfMonth(),
                            'created_at' => $month->copy()->startOfMonth()->addDays(2), 'updated_at' => $answered,
                        ]
                    );
                    SurveyResponse::firstOrCreate(
                        ['survey_id' => $csatSurvey->id],
                        [
                            'score' => max(1, min(5, (int) round($score / 2))),
                            'comment' => null,
                            'responded_at' => $answered,
                        ]
                    );
                }
            }
        }
    }

    /** Deterministic per-client satisfaction arc (0 = 11 months ago). */
    protected function npsScore(int $arc, int $monthsAgo, int $idx): int
    {
        $m = 11 - $monthsAgo; // 0 = oldest
        $jitter = (($idx * 7 + $m * 3) % 3) - 1; // -1..1
        $score = match ($arc) {
            0 => 9 + $jitter, // steady-high account
            1 => min(10, 5 + (int) round($m * 0.4) + $jitter), // improving
            2 => ($m === 5 || $m === 6) ? 4 + ($idx % 2) : 8 + $jitter, // dip then recovery
            3 => [6, 9, 7, 10, 6, 8, 7, 9, 6, 8, 7, 9][$m], // volatile
            default => 7 + ($idx % 2) + ($m % 2 === 0 ? 0 : 1), // steady-mid
        };

        return max(0, min(10, $score));
    }
}
