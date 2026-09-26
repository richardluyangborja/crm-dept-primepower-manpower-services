<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Followup;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmCompanySeeder extends Seeder
{
    /** 20 more companies with full data. Static, deterministic, no Faker (specs/12). */
    public function run(): void
    {
        $rep1 = User::where('email', 'rep.juandelacruz@primepower.ph')->firstOrFail();
        $rep2 = User::where('email', 'rep.mariasantos@primepower.ph')->firstOrFail();
        $nps = SurveyTemplate::firstOrCreate(['name' => 'Quarterly NPS'], ['type' => 'nps', 'questions' => [['q' => 'How likely are you to recommend Primepower?', 'scale' => 10]], 'is_active' => true]);

        $cos = [
            ['Cebu Port Services Inc.', 'Logistics', 'Cebu City', 'Cebu', 'ops@cebuport.ph', '+639201111111', 'Marco Uy', 'Operations Head', 'facebook', 45, 'stevedores'],
            ['Iloilo Supermart Chain', 'Retail', 'Iloilo City', 'Iloilo', 'hrd@iloilosuper.ph', '+639202222222', 'Rosa Diaz', 'HR Manager', 'walk_in', 60, 'cashiers'],
            ['Makati BPO Tower Corp.', 'BPO', 'Makati City', 'Metro Manila', 'admin@makatibpo.ph', '+639203333333', 'Kevin Sy', 'Facilities Head', 'referral', 120, 'janitors'],
            ['Davao Banana Exporters Co.', 'Manufacturing', 'Davao City', 'Davao del Sur', 'hr@davaobanana.ph', '+639204444444', 'Lorna Cruz', 'HR Officer', 'event', 35, 'packers'],
            ['Baguio Country Hotel', 'Hospitality', 'Baguio City', 'Benguet', 'admin@baguiocountry.ph', '+639205555555', 'Paolo Ramos', 'General Manager', 'website', 25, 'housekeepers'],
            ['Cavite Auto Assembly Inc.', 'Manufacturing', 'Rosario', 'Cavite', 'hrd@caviteauto.ph', '+639206666666', 'Daniel Chua', 'HR Manager', 'cold_call', 80, 'production aides'],
            ['Ortigas Call Center Hub', 'BPO', 'Pasig City', 'Metro Manila', 'proc@ortigascc.ph', '+639207777777', 'Mia Santos', 'Procurement Officer', 'referral', 150, 'security guards'],
            ['Boracay Beach Resort', 'Hospitality', 'Boracay', 'Aklan', 'hr@boracayresort.ph', '+639208888888', 'Jose Marie', 'Resort Manager', 'facebook', 20, 'waiters'],
            ['Batangas Port Logistics', 'Logistics', 'Batangas City', 'Batangas', 'ops@batangasport.ph', '+639209999999', 'Ramon Lim', 'Operations Head', 'website', 55, 'forklift operators'],
            ['Quezon Memorial Hospital', 'Healthcare', 'Quezon City', 'Metro Manila', 'hr@quezonmem.ph', '+639210000001', 'Dr. Ana Uy', 'Admin Director', 'referral', 40, 'ward aides'],
            ['Laguna Technopark Foods', 'Manufacturing', 'Biñan', 'Laguna', 'admin@lagunatech.ph', '+639210000002', 'Grace Ngo', 'HR Officer', 'event', 70, 'production crew'],
            ['Cebu IT Park Solutions', 'BPO', 'Cebu City', 'Cebu', 'hrd@cebuitpark.ph', '+639210000003', 'Paolo Villanueva', 'Facilities Head', 'website', 90, 'janitors'],
            ['Davao Gulf Cannery Corp.', 'Manufacturing', 'Davao City', 'Davao del Sur', 'hrd@davaogulf.ph', '+639210000004', 'Liza Ramos', 'HR Manager', 'cold_call', 65, 'cannery workers'],
            ['Manila Bay Cruise Terminal', 'Hospitality', 'Manila', 'Metro Manila', 'ops@manilabay.ph', '+639210000005', 'Capt. Reyes', 'Terminal Manager', 'referral', 30, 'porters'],
            ['Pampanga Clark Outlets', 'Retail', 'Clark', 'Pampanga', 'hrd@pampangaclark.ph', '+639210000006', 'Sofia Go', 'Store Manager', 'walk_in', 50, 'sales clerks'],
            ['Taguig Pharma Labs Inc.', 'Healthcare', 'Taguig', 'Metro Manila', 'admin@taguigpharma.ph', '+639210000007', 'Dr. Lim', 'Lab Director', 'website', 28, 'lab aides'],
            ['Ilocos Wind Farm Services', 'Logistics', 'Bangui', 'Ilocos Norte', 'ops@ilocoswind.ph', '+639210000008', 'Marco Corpuz', 'Site Lead', 'event', 22, 'technicians'],
            ['Alabang Town Center Mall', 'Retail', 'Muntinlupa', 'Metro Manila', 'hrd@alabangtc.ph', '+639210000009', 'Nina Castillo', 'Mall Admin', 'referral', 110, 'housekeepers'],
            ['Cagayan Pineapple Growers', 'Manufacturing', 'Tuguegarao', 'Cagayan', 'hr@cagayanpine.ph', '+639210000010', 'Rosa Aquino', 'HR Officer', 'facebook', 48, 'farm aides'],
            ['Subic Bay Freeport Hospital', 'Healthcare', 'Olongapo City', 'Zambales', 'admin@subicbayhosp.ph', '+639210000011', 'Dr. Santos', 'Chief of Clinics', 'website', 33, 'nursing aides'],
        ];
        $statuses = ['new', 'contacted', 'qualified', 'new', 'contacted', 'unqualified'];
        $stages = [
            ['stage' => 'negotiation', 'probability' => 80, 'days' => 25],
            ['stage' => 'proposal', 'probability' => 60, 'days' => 40],
            ['stage' => 'contacted', 'probability' => 20, 'days' => 12],
            ['stage' => 'qualified', 'probability' => 40, 'days' => 60],
            ['stage' => 'won', 'probability' => 100, 'days' => 120],
            ['stage' => 'new', 'probability' => 10, 'days' => 5],
        ];

        foreach ($cos as $i => [$name, $industry, $city, $province, $email, $phone, $contact, $position, $source, $heads, $positions]) {
            $owner = $i % 2 ? $rep2 : $rep1;
            $ago = 8 + ($i * 13) % 280;
            $at = now()->subDays($ago);
            $company = Company::firstOrCreate(
                ['name' => $name],
                ['owner_id' => $owner->id, 'industry' => $industry, 'address_city' => $city,
                    'address_province' => $province, 'contact_email' => $email, 'contact_phone' => $phone,
                    'source' => $source, 'created_at' => $at, 'updated_at' => $at]
            );

            $lead = Lead::firstOrCreate(
                ['company_id' => $company->id, 'contact_name' => $contact],
                ['owner_id' => $company->owner_id, 'company_name' => $name,
                    'contact_position' => $position, 'contact_email' => $email, 'contact_phone' => $phone,
                    'headcount_needed' => $heads, 'positions' => $positions, 'source' => $source,
                    'status' => $statuses[$i % count($statuses)], 'notes' => "Needs $heads $positions.",
                    'score' => \App\Services\Insights\LeadScorer::score(new Lead(['contact_email' => $email, 'contact_phone' => $phone, 'headcount_needed' => $heads])),
                    'created_at' => $at, 'updated_at' => $at]
            );

            // Open stages get a deal; every 5th company already won and has a client.
            $st = $stages[$i % count($stages)];
            $value = $heads * 15000 * 100;
            $dealAt = now()->subDays(max(1, $ago - 5));
            if ($st['stage'] === 'won') {
                $client = Client::firstOrCreate(
                    ['company_id' => $company->id],
                    ['owner_id' => $company->owner_id, 'name' => $name, 'industry' => $industry,
                        'address_city' => $city, 'address_province' => $province,
                        'contact_email' => $email, 'contact_phone' => $phone,
                        'status' => 'active', 'source' => $source,
                        'created_from_lead_id' => $lead->id, 'last_contacted_at' => now()->subDays(2),
                        'created_at' => $dealAt, 'updated_at' => $dealAt]
                );
                $lead->update(['status' => 'converted', 'converted_client_id' => $client->id]);
                Contact::firstOrCreate(
                    ['client_id' => $client->id, 'full_name' => $contact],
                    ['position' => $position, 'email' => $email, 'phone' => $phone, 'is_primary' => true]
                );
                $opp = Opportunity::firstOrCreate(
                    ['title' => "$heads $positions — $name"],
                    ['client_id' => $client->id, 'company_id' => $company->id, 'owner_id' => $company->owner_id,
                        'stage' => 'won', 'value_centavos' => $value, 'probability' => 100,
                        'headcount' => $heads, 'rate_per_head_centavos' => 1500000, 'contract_months' => 12,
                        'won_at' => now()->subDays(30 + ($i * 7) % 120),
                        'created_at' => $dealAt, 'updated_at' => now()->subDays(30 + ($i * 7) % 120)]
                );
                $ref = 'JO-2026-'.str_pad((string) (200 + $i), 4, '0', STR_PAD_LEFT);
                JobOrder::firstOrCreate(
                    ['ref' => $ref],
                    ['opportunity_id' => $opp->id, 'client_id' => $client->id, 'owner_id' => $client->owner_id,
                        'title' => "$heads $positions — $name", 'headcount' => $heads, 'value_centavos' => $value,
                        'status' => $i % 2 ? 'deployed' : 'staffed', 'invoice_ref' => 'INV-2026-'.str_pad((string) (200 + $i), 4, '0', STR_PAD_LEFT),
                        'payload' => ['mock' => true, 'seeded' => true], 'created_at' => $dealAt, 'updated_at' => $dealAt]
                );
                Contract::firstOrCreate(
                    ['ref' => 'CTR-2026-'.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT)],
                    ['opportunity_id' => $opp->id, 'client_id' => $client->id, 'owner_id' => $client->owner_id,
                        'headcount' => $heads, 'rate_per_head_centavos' => 1500000, 'contract_months' => 12,
                        'monthly_billing_centavos' => $heads * 1500000, 'contract_total_centavos' => $heads * 1500000 * 12,
                        'start_date' => now()->subDays(60 + ($i * 11) % 200)->toDateString(), 'status' => 'active',
                        'payload' => ['mock' => true, 'seeded' => true],
                        'created_at' => $dealAt, 'updated_at' => $dealAt]
                );
                Invoice::firstOrCreate(
                    ['ref' => 'INV-2026-'.str_pad((string) (200 + $i), 4, '0', STR_PAD_LEFT)],
                    ['opportunity_id' => $opp->id, 'client_id' => $client->id, 'owner_id' => $client->owner_id,
                        'title' => "$heads $positions — $name", 'amount_centavos' => $heads * 1500000,
                        'balance_centavos' => $i % 3 === 0 ? 0 : $heads * 1500000,
                        'status' => $i % 3 === 0 ? 'paid' : 'sent',
                        'due_at' => now()->addDays(20)->toDateString(),
                        'payload' => ['mock' => true, 'seeded' => true], 'created_at' => $dealAt, 'updated_at' => $dealAt]
                );
            } else {
                Opportunity::firstOrCreate(
                    ['title' => "$heads $positions — $name"],
                    ['company_id' => $company->id, 'owner_id' => $company->owner_id,
                        'stage' => $st['stage'], 'value_centavos' => $value, 'probability' => $st['probability'],
                        'headcount' => $heads, 'expected_close_date' => now()->addDays(30)->toDateString(),
                        'created_at' => $dealAt, 'updated_at' => now()->subDays($st['days'] % 45)]
                );
            }

            Activity::firstOrCreate(
                ['subject' => "Intro call — $name"],
                ['company_id' => $company->id, 'owner_id' => $company->owner_id, 'type' => 'call',
                    'body' => "Interested in $heads $positions.", 'outcome' => 'connected',
                    'occurred_at' => now()->subDays(max(1, $ago - 2)),
                    'created_at' => $at, 'updated_at' => $at]
            );
            if ($i % 2 === 0) {
                Followup::firstOrCreate(
                    ['title' => "Follow up — $name", 'company_id' => $company->id],
                    ['company_id' => $company->id, 'owner_id' => $company->owner_id,
                        'due_at' => now()->addDays(2 + ($i % 9)), 'priority' => 'medium', 'status' => 'open',
                        'created_at' => $at, 'updated_at' => $at]
                );
            }
            if ($nps && $i % 3 === 0) {
                $seedClientId = Client::where('company_id', $company->id)->value('id');
                if ($seedClientId) {
                    $survey = Survey::firstOrCreate(
                        ['token' => 'seeded-nps-co-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                        ['template_id' => $nps->id, 'client_id' => $seedClientId,
                            'sent_by' => $company->owner_id, 'channel' => 'link', 'status' => 'responded',
                            'due_at' => now()->addDays(7), 'created_at' => $at, 'updated_at' => $at]
                    );
                    SurveyResponse::firstOrCreate(
                        ['survey_id' => $survey->id],
                        ['score' => 6 + ($i % 5), 'comment' => 'Ok ang serbisyo, salamat.', 'responded_at' => now()->subDays(4 + ($i % 20))]
                    );
                }
            }
        }
    }
}
