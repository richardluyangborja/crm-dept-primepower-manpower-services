<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmCoreSeeder extends Seeder
{
    /** Static PH-localized demo data. No Faker — ever (specs/12). Dates spread 2025-09 → 2026-09. */
    public function run(): void
    {
        $rep1 = User::where('email', 'rep.juandelacruz@primepower.ph')->firstOrFail();
        $rep2 = User::where('email', 'rep.mariasantos@primepower.ph')->firstOrFail();

        $companies = [
            ['name' => 'BGC Tech Solutions Inc.', 'industry' => 'BPO', 'city' => 'Taguig', 'province' => 'Metro Manila', 'email' => 'hrd@bgctech.ph', 'phone' => '+639171111111', 'source' => 'referral', 'owner' => $rep1,
                'contact' => ['Paolo Gutierrez', 'IT Manager'], 'lead' => ['status' => 'qualified', 'notes' => 'Needs 40 service crew for BGC site.', 'days' => 12]],
            ['name' => 'Laguna Auto Parts Corp.', 'industry' => 'Manufacturing', 'city' => 'Calamba', 'province' => 'Laguna', 'email' => 'admin@lagunaauto.ph', 'phone' => '+639172222222', 'source' => 'website', 'owner' => $rep1,
                'contact' => ['Jose Reyes', 'HR Officer'], 'lead' => ['status' => 'contacted', 'notes' => 'Factory in Calamba, shifting schedule.', 'days' => 30]],
            ['name' => 'Iloilo Food Manufacturing Co.', 'industry' => 'Manufacturing', 'city' => 'Iloilo City', 'province' => 'Iloilo', 'email' => 'hr@iloilofood.ph', 'phone' => '+639173333333', 'source' => 'facebook', 'owner' => $rep2,
                'contact' => ['Ana Mendoza', 'Admin Assistant'], 'lead' => ['status' => 'new', 'notes' => null, 'days' => 3]],
            ['name' => 'BDO Unibank Inc.', 'industry' => 'BPO', 'city' => 'Makati City', 'province' => 'Metro Manila', 'email' => 'procurement@bdo-unibank.ph', 'phone' => '+639174444441', 'source' => 'referral', 'owner' => $rep1,
                'contact' => ['Ramon Cruz', 'Procurement Officer'], 'lead' => ['status' => 'contacted', 'notes' => 'Branch tellers for Ortigas cluster.', 'days' => 55],
                'client' => ['status' => 'active', 'days' => 290]],
            ['name' => 'Quezon City Retail Group', 'industry' => 'Retail', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'email' => 'hrd@qcretail.ph', 'phone' => '+639175555551', 'source' => 'walk_in', 'owner' => $rep1,
                'contact' => ['Liza Fernandez', 'Store Manager'], 'lead' => ['status' => 'new', 'notes' => null, 'days' => 8]],
            ['name' => 'Clark Industrial Estate Corp.', 'industry' => 'Logistics', 'city' => 'Clark', 'province' => 'Pampanga', 'email' => 'admin@clarkestate.ph', 'phone' => '+639176666661', 'source' => 'cold_call', 'owner' => $rep2,
                'contact' => ['Daniel Go', 'Operations Head'], 'lead' => ['status' => 'contacted', 'notes' => 'Warehouse staff, night shift.', 'days' => 90]],
            ['name' => 'Davao Agri Processing Co.', 'industry' => 'Manufacturing', 'city' => 'Davao City', 'province' => 'Davao del Sur', 'email' => 'hr@davaoagri.ph', 'phone' => '+639177777771', 'source' => 'event', 'owner' => $rep2,
                'contact' => ['Grace Lim', 'HR Manager'], 'lead' => ['status' => 'qualified', 'notes' => 'Met at Davao jobs fair; 60 packers needed.', 'days' => 150]],
            ['name' => 'Calamba Electronics Corp.', 'industry' => 'Manufacturing', 'city' => 'Calamba', 'province' => 'Laguna', 'email' => 'hrd@calambaelex.ph', 'phone' => '+639178888881', 'source' => 'website', 'owner' => $rep2,
                'contact' => ['Kevin Tan', 'HR Officer'], 'lead' => ['status' => 'new', 'notes' => null, 'days' => 20]],
            ['name' => 'Ayala Malls Manila Bay', 'industry' => 'Retail', 'city' => 'Parañaque', 'province' => 'Metro Manila', 'email' => 'admin@ayalamallmb.ph', 'phone' => '+639179999991', 'source' => 'referral', 'owner' => $rep1,
                'contact' => ['Sofia Reyes', 'Mall Admin'], 'lead' => ['status' => 'qualified', 'notes' => 'Housekeeping for mall opening.', 'days' => 200]],
            ['name' => 'Subic Shipyard Services', 'industry' => 'Logistics', 'city' => 'Olongapo City', 'province' => 'Zambales', 'email' => 'ops@subicship.ph', 'phone' => '+639170000001', 'source' => 'cold_call', 'owner' => $rep1,
                'contact' => ['Marco Santos', 'Operations Head'], 'lead' => ['status' => 'unqualified', 'notes' => 'No budget until next quarter.', 'days' => 260]],
            ['name' => 'SM Supermalls – Cebu', 'industry' => 'Retail', 'city' => 'Cebu City', 'province' => 'Cebu', 'email' => 'hrd@smcebu.ph', 'phone' => '+639174444444', 'source' => 'referral', 'owner' => $rep2,
                'contact' => ['Jen Aquino', 'HR Manager'], 'client' => ['status' => 'active', 'days' => 300]],
            ['name' => 'Davao Prime Hotel', 'industry' => 'Hospitality', 'city' => 'Davao City', 'province' => 'Davao del Sur', 'email' => 'admin@davaoprime.ph', 'phone' => '+639175555555', 'source' => 'walk_in', 'owner' => $rep1,
                'contact' => ['Mark Villanueva', 'HR Manager'], 'client' => ['status' => 'active', 'days' => 280]],
            ['name' => 'Subic Logistics Corp.', 'industry' => 'Logistics', 'city' => 'Olongapo City', 'province' => 'Zambales', 'email' => 'ops@subiclog.ph', 'phone' => '+639176666666', 'source' => 'cold_call', 'owner' => $rep1,
                'contact' => ['Carlos Lim', 'Operations Head'], 'client' => ['status' => 'prospect', 'days' => 40]],
            ['name' => 'Makati Medical Center', 'industry' => 'Healthcare', 'city' => 'Makati City', 'province' => 'Metro Manila', 'email' => 'hr@makatimed.ph', 'phone' => '+639177777777', 'source' => 'referral', 'owner' => $rep2,
                'contact' => ['Dr. Uy', 'Admin Director'], 'client' => ['status' => 'inactive', 'days' => 320]],
            ['name' => 'Cebu Pacific Catering Services', 'industry' => 'Hospitality', 'city' => 'Lapu-Lapu City', 'province' => 'Cebu', 'email' => 'hrd@cebucatering.ph', 'phone' => '+639178888882', 'source' => 'referral', 'owner' => $rep1,
                'contact' => ['Nina Torres', 'HR Officer'], 'client' => ['status' => 'inactive', 'days' => 310]],
            ['name' => 'Calamba Electronics Corp.', 'industry' => 'Manufacturing', 'city' => 'Calamba', 'province' => 'Laguna', 'email' => 'hrd@calambaelex.ph', 'phone' => '+639170000002', 'source' => 'website', 'owner' => $rep2,
                'contact' => ['Kevin Tan', 'HR Officer'], 'client' => ['status' => 'prospect', 'days' => 25]],
            ['name' => 'Quezon City Retail Group', 'industry' => 'Retail', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'email' => 'hrd@qcretail.ph', 'phone' => '+639171111112', 'source' => 'walk_in', 'owner' => $rep1,
                'contact' => ['Liza Fernandez', 'Store Manager'], 'client' => ['status' => 'prospect', 'days' => 15]],
        ];

        foreach ($companies as $c) {
            $created = now()->subDays($c['lead']['days'] ?? $c['client']['days'] ?? 30);
            $company = Company::firstOrCreate(
                ['name' => $c['name']],
                [
                    'owner_id' => $c['owner']->id, 'industry' => $c['industry'],
                    'address_city' => $c['city'], 'address_province' => $c['province'],
                    'contact_email' => $c['email'], 'contact_phone' => $c['phone'],
                    'source' => $c['source'], 'created_at' => $created, 'updated_at' => $created,
                ]
            );
            if (isset($c['lead'])) {
                $l = $c['lead'];
                $lead = Lead::firstOrCreate(
                    ['company_id' => $company->id, 'contact_name' => $c['contact'][0]],
                    [
                        'owner_id' => $company->owner_id, 'company_name' => $company->name,
                        'contact_position' => $c['contact'][1], 'contact_email' => $company->contact_email,
                        'contact_phone' => $company->contact_phone, 'source' => $company->source,
                        'status' => $l['status'], 'notes' => $l['notes'],
                        'score' => \App\Services\Insights\LeadScorer::score(new Lead(['contact_email' => $company->contact_email, 'contact_phone' => $company->contact_phone])),
                        'created_at' => $created, 'updated_at' => $created,
                    ]
                );
            }
            if (isset($c['client'])) {
                $cl = $c['client'];
                $client = Client::firstOrCreate(
                    ['company_id' => $company->id],
                    [
                        'owner_id' => $company->owner_id, 'name' => $company->name,
                        'industry' => $company->industry, 'address_city' => $company->address_city,
                        'address_province' => $company->address_province, 'contact_email' => $company->contact_email,
                        'contact_phone' => $company->contact_phone, 'status' => $cl['status'], 'source' => $company->source,
                        'last_contacted_at' => $cl['status'] === 'inactive' ? now()->subDays(45) : now()->subDays(3),
                        'created_at' => $created, 'updated_at' => $created,
                    ]
                );
                Contact::firstOrCreate(
                    ['client_id' => $client->id, 'full_name' => $c['contact'][0]],
                    ['position' => $c['contact'][1], 'email' => $company->contact_email, 'phone' => $company->contact_phone, 'is_primary' => true]
                );
            }
        }

        // Secondary contacts at larger accounts.
        $extra = [
            ['client' => 'SM Supermalls – Cebu', 'full_name' => 'Paolo Gutierrez', 'position' => 'Procurement Head', 'email' => 'proc@smcebu.ph', 'phone' => '+639174444445'],
            ['client' => 'Davao Prime Hotel', 'full_name' => 'Ana Mendoza', 'position' => 'Admin Officer', 'email' => 'admin2@davaoprime.ph', 'phone' => '+639175555556'],
            ['client' => 'BDO Unibank Inc.', 'full_name' => 'Jose Reyes', 'position' => 'Procurement Head', 'email' => 'proc2@bdo-unibank.ph', 'phone' => '+639179999993'],
            ['client' => 'Makati Medical Center', 'full_name' => 'Marites Reyes', 'position' => 'Admin Officer', 'email' => 'admin2@makatimed.ph', 'phone' => '+639177777778'],
        ];
        foreach ($extra as $e) {
            $client = Client::where('name', $e['client'])->first();
            if (! $client) continue;
            Contact::firstOrCreate(
                ['client_id' => $client->id, 'full_name' => $e['full_name']],
                ['position' => $e['position'], 'email' => $e['email'], 'phone' => $e['phone'], 'is_primary' => false]
            );
        }
    }
}
