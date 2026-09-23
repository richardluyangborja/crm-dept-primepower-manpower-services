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
    /** Dates spread 2025-09 → 2026-09; rows linked to their client's company. */
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
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '120 janitors — SM Cebu', 'stage' => 'negotiation', 'value_centavos' => 480000000, 'probability' => 80, 'expected_close_date' => now()->addDays(20)->toDateString(), 'ca' => 40, 'ua' => 3],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => '80 security guards — Davao Prime', 'stage' => 'proposal', 'value_centavos' => 240000000, 'probability' => 60, 'expected_close_date' => now()->addDays(35)->toDateString(), 'ca' => 30, 'ua' => 6],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '50 cashiers — SM Cebu (lost)', 'stage' => 'lost', 'value_centavos' => 150000000, 'probability' => 0, 'lost_reason' => 'Chose competitor pricing', 'lost_at' => now()->subDays(10), 'ca' => 280, 'ua' => 10],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'title' => '30 tellers — BDO Ortigas', 'stage' => 'qualified', 'value_centavos' => 190000000, 'probability' => 40, 'expected_close_date' => now()->addDays(45)->toDateString(), 'ca' => 50, 'ua' => 12],
            ['client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'title' => '50 production aides — Calamba', 'stage' => 'contacted', 'value_centavos' => 175000000, 'probability' => 20, 'expected_close_date' => now()->addDays(60)->toDateString(), 'ca' => 25, 'ua' => 4],
            ['client_id' => $qc->id, 'owner_id' => $qc->owner_id, 'title' => '25 merchandisers — QC Retail', 'stage' => 'new', 'value_centavos' => 90000000, 'probability' => 10, 'expected_close_date' => now()->addDays(50)->toDateString(), 'ca' => 8, 'ua' => 8],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => '40 housekeepers — Davao Prime', 'stage' => 'negotiation', 'value_centavos' => 160000000, 'probability' => 80, 'expected_close_date' => now()->addDays(15)->toDateString(), 'ca' => 35, 'ua' => 2],
            ['client_id' => $catering->id, 'owner_id' => $catering->owner_id, 'title' => '35 commissary crew — Cebu Catering (lost)', 'stage' => 'lost', 'value_centavos' => 140000000, 'probability' => 0, 'lost_reason' => 'No response after quotation', 'lost_at' => now()->subDays(40), 'ca' => 300, 'ua' => 40],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => '60 promo staff — SM Cebu (won)', 'stage' => 'won', 'value_centavos' => 210000000, 'probability' => 100, 'won_at' => now()->subDays(20), 'ca' => 150, 'ua' => 20],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'title' => '15 messengers — BDO Makati', 'stage' => 'proposal', 'value_centavos' => 75000000, 'probability' => 60, 'expected_close_date' => now()->addDays(25)->toDateString(), 'ca' => 20, 'ua' => 5],
        ];
        foreach ($opps as $o) {
            $ca = now()->subDays($o['ca']);
            $ua = now()->subDays($o['ua']);
            unset($o['ca'], $o['ua']);
            $row = Opportunity::firstOrCreate(['title' => $o['title']], $o);
            $client = Client::find($row->client_id);
            $row->update([
                'company_id' => $client?->company_id, 'created_at' => $ca, 'updated_at' => $ua,
            ]);
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
            $row = Activity::firstOrCreate(['subject' => $a['subject']], $a);
            $row->update([
                'company_id' => Client::find($row->client_id)?->company_id,
                'created_at' => $row->occurred_at, 'updated_at' => $row->occurred_at,
            ]);
        }

        $tpl = SurveyTemplate::firstOrCreate(['name' => 'Quarterly NPS'], ['type' => 'nps', 'questions' => [['q' => 'How likely are you to recommend Primepower?', 'scale' => 10]], 'is_active' => true]);
        SurveyTemplate::firstOrCreate(['name' => 'Deployment CSAT'], ['type' => 'csat', 'questions' => [['q' => 'How satisfied are you with the deployment?', 'scale' => 5]], 'is_active' => true]);
        $csatTpl = SurveyTemplate::firstOrCreate(['name' => 'Post-visit check'], ['type' => 'custom', 'questions' => [['q' => 'Was the visit helpful?', 'options' => ['Yes', 'Somewhat', 'No']]] , 'is_active' => true]);

        $surveySeeds = [
            ['token' => 'seeded-nps-sm-cebu-token-00000001', 'template_id' => $tpl->id, 'client_id' => $sm->id, 'sent_by' => $sm->owner_id, 'score' => 9, 'comment' => 'Mabilis ang deployment, salamat!', 'sent' => 12, 'answered' => 1],
            ['token' => 'seeded-nps-makatimed-token-00000002', 'template_id' => $tpl->id, 'client_id' => $med->id, 'sent_by' => $rep2->id, 'score' => 5, 'comment' => 'Paki-follow up ang billing, thanks.', 'sent' => 20, 'answered' => 2],
            ['token' => 'seeded-nps-hotel-token-00000003', 'template_id' => $tpl->id, 'client_id' => $hotel->id, 'sent_by' => $hotel->owner_id, 'score' => 10, 'comment' => 'Excellent guards, very professional!', 'sent' => 40, 'answered' => 5],
            ['token' => 'seeded-nps-catering-token-00000004', 'template_id' => $tpl->id, 'client_id' => $catering->id, 'sent_by' => $catering->owner_id, 'score' => 4, 'comment' => 'Mabagal ang response sa quotation.', 'sent' => 90, 'answered' => 30],
            ['token' => 'seeded-csat-bdo-token-00000005', 'template_id' => $csatTpl->id, 'client_id' => $bdo->id, 'sent_by' => $bdo->owner_id, 'score' => 8, 'comment' => 'Maayos ang coordination.', 'sent' => 15, 'answered' => 3],
            ['token' => 'seeded-nps-smcebu-token-00000006', 'client_id' => $sm->id, 'pending' => true, 'sent' => 7],
            ['token' => 'seeded-nps-qc-token-00000007', 'client_id' => $qc->id, 'pending' => true, 'sent' => 5],
            ['token' => 'seeded-csat-calamba-token-00000008', 'template_id' => $csatTpl->id, 'client_id' => $calamba->id, 'pending' => true, 'sent' => 3],
        ];
        foreach ($surveySeeds as $s) {
            $client = Client::find($s['client_id']);
            $survey = Survey::firstOrCreate(
                ['token' => $s['token']],
                ['template_id' => $s['template_id'] ?? $tpl->id, 'client_id' => $s['client_id'], 'sent_by' => $s['sent_by'] ?? $client->owner_id, 'channel' => 'link', 'status' => isset($s['pending']) ? 'sent' : 'responded', 'due_at' => now()->addDays(7),
                    'created_at' => now()->subDays($s['sent']), 'updated_at' => now()->subDays($s['sent'])]
            );
            if (! isset($s['pending'])) {
                SurveyResponse::firstOrCreate(
                    ['survey_id' => $survey->id],
                    ['score' => $s['score'], 'comment' => $s['comment'], 'responded_at' => now()->subDays($s['answered'])]
                );
            }
        }

        // Journey seeds (specs/18 §3A): Davao Prime walks staffing stages out of the box.
        $wonOpp = Opportunity::where('title', '80 security guards — Davao Prime')->first();
        $smWon = Opportunity::where('title', '60 promo staff — SM Cebu (won)')->first();
        $seedJobs = [
            ['opportunity_id' => $wonOpp?->id, 'client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'ref' => 'JO-2026-0101', 'title' => '80 security guards — Davao Prime', 'headcount' => 60, 'value_centavos' => 240000000, 'status' => 'deployed', 'invoice_ref' => 'INV-2026-0101', 'ca' => 45],
            ['opportunity_id' => $smWon?->id, 'client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'ref' => 'JO-2026-0102', 'title' => '60 promo staff — SM Cebu', 'headcount' => 45, 'value_centavos' => 210000000, 'status' => 'staffed', 'invoice_ref' => 'INV-2026-0102', 'ca' => 60],
        ];
        foreach ($seedJobs as $j) {
            if (! $j['opportunity_id']) continue;
            $ca = now()->subDays($j['ca']);
            unset($j['ca']);
            $row = \App\Models\JobOrder::firstOrCreate(
                ['ref' => $j['ref']],
                $j + ['payload' => ['mock' => true, 'seeded' => true], 'created_at' => $ca, 'updated_at' => $ca]
            );
            $row->update(['created_at' => $ca, 'updated_at' => $ca]);
        }

        // Signed contract behind the Davao deployment (mock Core-3/Governance/Facilities).
        if ($wonOpp) {
            \App\Models\Contract::firstOrCreate(
                ['ref' => 'CTR-2026-0001'],
                [
                    'opportunity_id' => $wonOpp->id,
                    'client_id' => $hotel->id,
                    'owner_id' => $hotel->owner_id,
                    'headcount' => 60,
                    'rate_per_head_centavos' => 4000000,
                    'contract_months' => 12,
                    'monthly_billing_centavos' => 240000000,
                    'contract_total_centavos' => 2880000000,
                    'start_date' => now()->subDays(50)->toDateString(),
                    'status' => 'active',
                    'payload' => ['mock' => true, 'seeded' => true, 'depts' => ['core3_docs', 'governance_legal', 'facilities_contracts']],
                    'created_at' => now()->subDays(55), 'updated_at' => now()->subDays(55),
                ]
            );
        }

        // Phase 2B: mock AR across aging buckets (deterministic refs, no Faker).
        $med = Client::where('name', 'Makati Medical Center')->firstOrFail();
        $seedInvoices = [
            ['opportunity_id' => $wonOpp?->id, 'client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'ref' => 'INV-2026-0101', 'title' => '80 security guards — Davao Prime', 'amount_centavos' => 240000000, 'balance_centavos' => 240000000, 'status' => 'sent', 'due_at' => now()->addDays(20)->toDateString(), 'ca' => 25],
            ['opportunity_id' => $smWon?->id, 'client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'ref' => 'INV-2026-0102', 'title' => '60 promo staff — SM Cebu', 'amount_centavos' => 210000000, 'balance_centavos' => 60000000, 'status' => 'sent', 'due_at' => now()->subDays(10)->toDateString(), 'ca' => 40],
            ['opportunity_id' => null, 'client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'ref' => 'INV-2026-0103', 'title' => 'Promo booth staff — SM Cebu (Q3)', 'amount_centavos' => 90000000, 'balance_centavos' => 0, 'status' => 'paid', 'due_at' => now()->subDays(60)->toDateString(), 'ca' => 200],
            ['opportunity_id' => null, 'client_id' => $med->id, 'owner_id' => $med->owner_id, 'ref' => 'INV-2026-0104', 'title' => 'Ward aides — Makati Med (Q2)', 'amount_centavos' => 320000000, 'balance_centavos' => 320000000, 'status' => 'overdue', 'due_at' => now()->subDays(75)->toDateString(), 'ca' => 250],
            ['opportunity_id' => null, 'client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'ref' => 'INV-2026-0105', 'title' => 'Line relievers — Calamba (draft)', 'amount_centavos' => 120000000, 'balance_centavos' => 120000000, 'status' => 'draft', 'due_at' => now()->addDays(45)->toDateString(), 'ca' => 10],
        ];
        foreach ($seedInvoices as $inv) {
            $ca = now()->subDays($inv['ca']);
            unset($inv['ca']);
            $row = \App\Models\Invoice::firstOrCreate(
                ['ref' => $inv['ref']],
                $inv + ['payload' => ['mock' => true, 'seeded' => true], 'created_at' => $ca, 'updated_at' => $ca]
            );
            $row->update(['created_at' => $ca, 'updated_at' => $ca]);
        }

        $fups = [
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'Follow up quotation — SM Cebu headcount', 'due_at' => now()->addHours(3), 'priority' => 'high', 'status' => 'open', 'ca' => 1],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => 'Confirm reliever for Sunday shift', 'due_at' => now()->subHours(26), 'priority' => 'high', 'status' => 'overdue', 'ca' => 3],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'Send billing statement copy', 'due_at' => now()->addDay(), 'priority' => 'medium', 'status' => 'snoozed', 'snoozed_until' => now()->addDay(), 'ca' => 2],
            ['client_id' => $bdo->id, 'owner_id' => $bdo->owner_id, 'title' => 'Call back — BDO teller headcount', 'due_at' => now()->addHours(5), 'priority' => 'high', 'status' => 'open', 'ca' => 1],
            ['client_id' => $calamba->id, 'owner_id' => $calamba->owner_id, 'title' => 'Send rate card — Calamba', 'due_at' => now()->subHours(50), 'priority' => 'medium', 'status' => 'overdue', 'ca' => 4],
            ['client_id' => $qc->id, 'owner_id' => $qc->owner_id, 'title' => 'Visit QC Retail office', 'due_at' => now()->addDays(2), 'priority' => 'low', 'status' => 'open', 'ca' => 1],
            ['client_id' => $hotel->id, 'owner_id' => $hotel->owner_id, 'title' => 'Contract signing — housekeepers', 'due_at' => now()->subDays(3), 'priority' => 'high', 'status' => 'done', 'ca' => 9],
            ['client_id' => $catering->id, 'owner_id' => $catering->owner_id, 'title' => 'Re-engage Cebu Catering', 'due_at' => now()->subDays(4), 'priority' => 'medium', 'status' => 'escalated', 'escalated_to' => $rep2->id, 'ca' => 12],
            ['client_id' => $med->id, 'owner_id' => $med->owner_id, 'title' => 'Send SOA copy — Makati Med', 'due_at' => now()->addHours(8), 'priority' => 'high', 'status' => 'open', 'ca' => 1],
            ['client_id' => $sm->id, 'owner_id' => $sm->owner_id, 'title' => 'December seasonal crew proposal', 'due_at' => now()->addDays(4), 'priority' => 'medium', 'status' => 'open', 'ca' => 2],
        ];
        foreach ($fups as $f) {
            $ca = now()->subDays($f['ca']);
            unset($f['ca']);
            $row = Followup::firstOrCreate(['title' => $f['title']], $f + ['created_at' => $ca, 'updated_at' => $ca]);
            $client = Client::find($row->client_id);
            $row->update(['company_id' => $client?->company_id, 'created_at' => $ca, 'updated_at' => $ca]);
        }
    }
}
