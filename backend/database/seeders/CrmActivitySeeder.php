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
        $bdo = Client::where('name', 'BDO Unibank Inc.')->firstOrFail();
        $catering = Client::where('name', 'Cebu Pacific Catering Services')->firstOrFail();
        $calamba = Client::where('name', 'Calamba Electronics Corp.')->firstOrFail();
        $qc = Client::where('name', 'Quezon City Retail Group')->firstOrFail();
        $rep2 = User::where('email', 'rep.mariasantos@primepower.ph')->firstOrFail();

        $opps = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '120 janitors — SM Cebu', 'stage' => 'negotiation', 'value_centavos' => 480000000, 'probability' => 80, 'expected_close_date' => now()->addDays(20)->toDateString()],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => '80 security guards — Davao Prime', 'stage' => 'proposal', 'value_centavos' => 240000000, 'probability' => 60, 'expected_close_date' => now()->addDays(35)->toDateString()],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '50 cashiers — SM Cebu (lost)', 'stage' => 'lost', 'value_centavos' => 150000000, 'probability' => 0, 'lost_reason' => 'Chose competitor pricing', 'lost_at' => now()->subDays(10)],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'title' => '30 tellers — BDO Ortigas', 'stage' => 'qualified', 'value_centavos' => 190000000, 'probability' => 40, 'expected_close_date' => now()->addDays(45)->toDateString()],
            ['client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'title' => '50 production aides — Calamba', 'stage' => 'contacted', 'value_centavos' => 175000000, 'probability' => 20, 'expected_close_date' => now()->addDays(60)->toDateString()],
            ['client_id' => $qc->id, 'owner_id' => $qc->owner_id, 'title' => '25 merchandisers — QC Retail', 'stage' => 'new', 'value_centavos' => 90000000, 'probability' => 10, 'expected_close_date' => now()->addDays(50)->toDateString()],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => '40 housekeepers — Davao Prime', 'stage' => 'negotiation', 'value_centavos' => 160000000, 'probability' => 80, 'expected_close_date' => now()->addDays(15)->toDateString()],
            ['client_id' => $catering->id, 'owner_id' => $catering->owner_id, 'title' => '35 commissary crew — Cebu Catering (lost)', 'stage' => 'lost', 'value_centavos' => 140000000, 'probability' => 0, 'lost_reason' => 'No response after quotation', 'lost_at' => now()->subDays(40)],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '60 promo staff — SM Cebu (won)', 'stage' => 'won', 'value_centavos' => 210000000, 'probability' => 100, 'won_at' => now()->subDays(20)],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'title' => '15 messengers — BDO Makati', 'stage' => 'proposal', 'value_centavos' => 75000000, 'probability' => 60, 'expected_close_date' => now()->addDays(25)->toDateString()],
        ];
        foreach ($opps as $o) {
            Opportunity::firstOrCreate(['title' => $o['title']], $o);
        }

        $acts = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'type' => 'site_visit', 'subject' => 'Site visit — SM Cebu', 'body' => 'Guard shifting discussed with mall admin.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(2)],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'type' => 'email', 'subject' => 'Quotation sent — 120 janitors', 'body' => 'Sent revised quotation with night differential.', 'outcome' => 'sent', 'occurred_at' => now()->subDay()],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'type' => 'call', 'subject' => 'Follow-up call — guard deployment', 'body' => 'Mabilis ang deployment, salamat! Request reliever for Sunday.', 'outcome' => 'connected', 'occurred_at' => now()->subHours(5)],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'type' => 'meeting', 'subject' => 'Discovery meeting — BDO Ortigas', 'body' => 'Needs 30 tellers; decision timeline end of quarter.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(4)],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'type' => 'email', 'subject' => 'Quotation sent — 30 tellers', 'body' => 'Sent quotation with shifting options.', 'outcome' => 'sent', 'occurred_at' => now()->subDays(3)],
            ['client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'type' => 'call', 'subject' => 'Intro call — Calamba Electronics', 'body' => 'Interested in 50 production aides for night shift.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(6)],
            ['client_id' => $qc->id, 'owner_id' => $qc->owner_id, 'type' => 'note', 'subject' => 'Walk-in inquiry logged', 'body' => 'QC Retail visited the office; asked about merchandisers.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(8)],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'type' => 'site_visit', 'subject' => 'Site visit — Davao Prime housekeeping', 'body' => 'Inspected lobby and rooms floors; 40 staff estimate confirmed.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(5)],
            ['client_id' => $med->id, 'owner_id' => $med->owner_id, 'type' => 'call', 'subject' => 'Billing follow-up — Makati Med', 'body' => 'Paki-follow up ang billing, thanks. Client requested SOA copy.', 'outcome' => 'callback', 'occurred_at' => now()->subDays(12)],
            ['client_id' => $catering->id, 'owner_id' => $catering->owner_id, 'type' => 'email', 'subject' => 'Quotation sent — commissary crew', 'body' => 'No response after quotation; for win/loss tracking.', 'outcome' => 'sent', 'occurred_at' => now()->subDays(45)],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'type' => 'call', 'subject' => 'Promo staff deployment check', 'body' => 'Ok ang guards pero need reliever pag Sunday.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(22)],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'type' => 'call', 'subject' => 'Messenger headcount check', 'body' => 'Confirmed 15 messengers for Makati head office.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(9)],
            ['client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'type' => 'meeting', 'subject' => 'Plant tour — Calamba', 'body' => 'Toured production lines; shifting schedule discussed.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(15)],
            ['client_id' => $qc->id, 'owner_id' => $qc->owner_id, 'type' => 'call', 'subject' => 'Merchandiser rates inquiry', 'body' => 'Asked about daily rates; sent rate card.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(18)],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'type' => 'email', 'subject' => 'Contract draft — 40 housekeepers', 'body' => 'Sent draft contract for review.', 'outcome' => 'sent', 'occurred_at' => now()->subDays(7)],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'type' => 'note', 'subject' => 'Holiday staffing heads-up', 'body' => 'Mall expects +30% foot traffic in December; propose seasonal crew.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(30)],
            ['client_id' => $med->id, 'owner_id' => $med->owner_id, 'type' => 'meeting', 'subject' => 'QBR — Makati Med account', 'body' => 'Quarterly review; raised reliever coverage on Sundays.', 'outcome' => 'connected', 'occurred_at' => now()->subDays(50)],
            ['client_id' => $catering->id, 'owner_id' => $catering->owner_id, 'type' => 'call', 'subject' => 'Re-engagement attempt', 'body' => 'No answer; left voicemail for Nina Torres.', 'outcome' => 'no-answer', 'occurred_at' => now()->subDays(20)],
        ];
        foreach ($acts as $a) {
            Activity::firstOrCreate(['subject' => $a['subject']], $a);
        }

        $tpl = SurveyTemplate::firstOrCreate(['name' => 'Quarterly NPS'], ['type' => 'nps', 'questions' => [['q' => 'How likely are you to recommend PrimePower?', 'scale' => 10]], 'is_active' => true]);
        SurveyTemplate::firstOrCreate(['name' => 'Deployment CSAT'], ['type' => 'csat', 'questions' => [['q' => 'How satisfied are you with the deployment?', 'scale' => 5]], 'is_active' => true]);
        $csatTpl = SurveyTemplate::firstOrCreate(['name' => 'Post-visit check'], ['type' => 'custom', 'questions' => [['q' => 'Was the visit helpful?', 'options' => ['Yes', 'Somewhat', 'No']]] , 'is_active' => true]);

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

        $extraSurveys = [
            ['token' => 'seeded-nps-hotel-token-00000003', 'template_id' => $tpl->id, 'client_id' => $hotel->id, 'sent_by' => $hotel->owner_id, 'score' => 10, 'comment' => 'Excellent guards, very professional!', 'days' => 5],
            ['token' => 'seeded-nps-catering-token-00000004', 'template_id' => $tpl->id, 'client_id' => $catering->id, 'sent_by' => $catering->owner_id, 'score' => 4, 'comment' => 'Mabagal ang response sa quotation.', 'days' => 30],
            ['token' => 'seeded-csat-bdo-token-00000005', 'template_id' => $csatTpl->id, 'client_id' => $bdo->id, 'sent_by' => $bdo->owner_id, 'score' => 8, 'comment' => 'Maayos ang coordination.', 'days' => 3],
            ['token' => 'seeded-nps-smcebu-token-00000006', 'client_id' => $sm->id, 'pending' => true, 'days' => -7],
            ['token' => 'seeded-nps-qc-token-00000007', 'client_id' => $qc->id, 'pending' => true, 'days' => -5],
            ['token' => 'seeded-csat-calamba-token-00000008', 'template_id' => $csatTpl->id, 'client_id' => $calamba->id, 'pending' => true, 'days' => -3],
        ];
        foreach ($extraSurveys as $s) {
            $survey = Survey::firstOrCreate(
                ['token' => $s['token']],
                ['template_id' => $s['template_id'] ?? $tpl->id, 'client_id' => $s['client_id'], 'sent_by' => Client::find($s['client_id'])->owner_id, 'channel' => 'link', 'status' => isset($s['pending']) ? 'sent' : 'responded', 'due_at' => now()->addDays(7)]
            );
            if (! isset($s['pending'])) {
                SurveyResponse::firstOrCreate(['survey_id' => $survey->id], ['score' => $s['score'], 'comment' => $s['comment'], 'responded_at' => now()->subDays($s['days'])]);
            }
        }

        $fups = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'Follow up quotation — SM Cebu headcount', 'due_at' => now()->addHours(3), 'priority' => 'high', 'status' => 'open'],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => 'Confirm reliever for Sunday shift', 'due_at' => now()->subHours(26), 'priority' => 'high', 'status' => 'overdue'],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'Send billing statement copy', 'due_at' => now()->addDay(), 'priority' => 'medium', 'status' => 'snoozed', 'snoozed_until' => now()->addDay()],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'title' => 'Call back — BDO teller headcount', 'due_at' => now()->addHours(5), 'priority' => 'high', 'status' => 'open'],
            ['client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'title' => 'Send rate card — Calamba', 'due_at' => now()->subHours(50), 'priority' => 'medium', 'status' => 'overdue'],
            ['client_id' => $qc->id, 'owner_id' => $qc->owner_id, 'title' => 'Visit QC Retail office', 'due_at' => now()->addDays(2), 'priority' => 'low', 'status' => 'open'],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => 'Contract signing — housekeepers', 'due_at' => now()->subDays(3), 'priority' => 'high', 'status' => 'done'],
            ['client_id' => $catering->id, 'owner_id' => $catering->owner_id, 'title' => 'Re-engage Cebu Catering', 'due_at' => now()->subDays(4), 'priority' => 'medium', 'status' => 'escalated', 'escalated_to' => $rep2->id],
            ['client_id' => $med->id, 'owner_id' => $med->owner_id, 'title' => 'Send SOA copy — Makati Med', 'due_at' => now()->addHours(8), 'priority' => 'high', 'status' => 'open'],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'December seasonal crew proposal', 'due_at' => now()->addDays(4), 'priority' => 'medium', 'status' => 'open'],
        ];
        foreach ($fups as $f) {
            Followup::firstOrCreate(['title' => $f['title']], $f);
        }
    }
}
