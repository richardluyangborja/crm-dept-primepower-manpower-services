<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Followup;
use App\Models\Opportunity;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmActivitySeeder extends Seeder
{
    public function run(): void
    {
        $sm = Client::where('name', 'SM Supermalls – Cebu')->firstOrFail();
        $hotel = Client::where('name', 'Davao Prime Hotel')->firstOrFail();
        $med = Client::where('name', 'Makati Medical Center')->firstOrFail();
        $rep2 = User::where('email', 'rep.mariasantos@primepower.ph')->firstOrFail();

        $opps = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '120 janitors — SM Cebu', 'stage' => 'negotiation', 'value_centavos' => 480000000, 'probability' => 80, 'expected_close_date' => now()->addDays(20)->toDateString()],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => '80 security guards — Davao Prime', 'stage' => 'proposal', 'value_centavos' => 240000000, 'probability' => 60, 'expected_close_date' => now()->addDays(35)->toDateString()],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '50 cashiers — SM Cebu (lost)', 'stage' => 'lost', 'value_centavos' => 150000000, 'probability' => 0, 'lost_reason' => 'Chose competitor pricing', 'lost_at' => now()->subDays(10)],
        ];
        foreach ($opps as $o) {
            Opportunity::firstOrCreate(['title' => $o['title']], $o);
        }

        $acts = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'type' => 'site_visit', 'subject' => 'Site visit — SM Cebu', 'body' => 'Guard shifting discussed with mall admin.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(2)],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'type' => 'email', 'subject' => 'Quotation sent — 120 janitors', 'body' => 'Sent revised quotation with night differential.', 'outcome' => 'sent', 'occurred_at' => now()->subDay()],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'type' => 'call', 'subject' => 'Follow-up call — guard deployment', 'body' => 'Mabilis ang deployment, salamat! Request reliever for Sunday.', 'outcome' => 'connected', 'occurred_at' => now()->subHours(5)],
        ];
        foreach ($acts as $a) {
            Activity::firstOrCreate(['subject' => $a['subject']], $a);
        }

        $tpl = SurveyTemplate::firstOrCreate(['name' => 'Quarterly NPS'], ['type' => 'nps', 'questions' => [['q' => 'How likely are you to recommend PrimePower?', 'scale' => 10]], 'is_active' => true]);
        SurveyTemplate::firstOrCreate(['name' => 'Deployment CSAT'], ['type' => 'csat', 'questions' => [['q' => 'How satisfied are you with the deployment?', 'scale' => 5]], 'is_active' => true]);

        $survey = Survey::firstOrCreate(
            ['token' => 'seeded-nps-sm-cebu-token-00000001'],
            ['template_id' => $tpl->id, 'client_id' => $sm->id, 'sent_by' => $sm->owner_id, 'channel' => 'link', 'status' => 'responded', 'due_at' => now()->addDays(7)]
        );
        SurveyResponse::firstOrCreate(['survey_id' => $survey->id], ['score' => 9, 'comment' => 'Mabilis ang deployment, salamat!', 'responded_at' => now()->subDay()]);

        $low = Survey::firstOrCreate(
            ['token' => 'seeded-nps-makatimed-token-00000002'],
            ['template_id' => $tpl->id, 'client_id' => $med->id, 'sent_by' => $rep2->id, 'channel' => 'link', 'status' => 'responded', 'due_at' => now()->addDays(7)]
        );
        SurveyResponse::firstOrCreate(['survey_id' => $low->id], ['score' => 5, 'comment' => 'Paki-follow up ang billing, thanks.', 'responded_at' => now()->subDays(2)]);

        $fups = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'Follow up quotation — SM Cebu headcount', 'due_at' => now()->addHours(3), 'priority' => 'high', 'status' => 'open'],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => 'Confirm reliever for Sunday shift', 'due_at' => now()->subHours(26), 'priority' => 'high', 'status' => 'overdue'],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'Send billing statement copy', 'due_at' => now()->addDay(), 'priority' => 'medium', 'status' => 'snoozed', 'snoozed_until' => now()->addDay()],
        ];
        foreach ($fups as $f) {
            Followup::firstOrCreate(['title' => $f['title']], $f);
        }
    }
}
