<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmCoreSeeder extends Seeder
{
    /** Static PH-localized demo data. No Faker — ever (specs/12). */
    public function run(): void
    {
        $rep1 = User::where('email', 'rep.juandelacruz@primepower.ph')->firstOrFail();
        $rep2 = User::where('email', 'rep.mariasantos@primepower.ph')->firstOrFail();

        $leads = [
            ['company_name' => 'BGC Tech Solutions Inc.', 'contact_name' => 'Paolo Gutierrez', 'contact_email' => 'hrd@bgctech.ph', 'contact_phone' => '+639171111111', 'source' => 'referral', 'status' => 'qualified', 'notes' => 'Needs 40 service crew for BGC site.'],
            ['company_name' => 'Laguna Auto Parts Corp.', 'contact_name' => 'Jose Reyes', 'contact_email' => 'admin@lagunaauto.ph', 'contact_phone' => '+639172222222', 'source' => 'website', 'status' => 'contacted', 'notes' => 'Factory in Calamba, shifting schedule.'],
            ['company_name' => 'Iloilo Food Manufacturing Co.', 'contact_name' => 'Ana Mendoza', 'contact_email' => 'hr@iloilofood.ph', 'contact_phone' => '+639173333333', 'source' => 'facebook', 'status' => 'new', 'notes' => null],
            ['company_name' => 'BDO Unibank Inc.', 'contact_name' => 'Ramon Cruz', 'contact_email' => 'procurement@bdo-unibank.ph', 'contact_phone' => '+639174444441', 'source' => 'referral', 'status' => 'contacted', 'notes' => 'Branch tellers for Ortigas cluster.'],
            ['company_name' => 'Quezon City Retail Group', 'contact_name' => 'Liza Fernandez', 'contact_email' => 'hrd@qcretail.ph', 'contact_phone' => '+639175555551', 'source' => 'walk_in', 'status' => 'new', 'notes' => null],
            ['company_name' => 'Clark Industrial Estate Corp.', 'contact_name' => 'Daniel Go', 'contact_email' => 'admin@clarkestate.ph', 'contact_phone' => '+639176666661', 'source' => 'cold_call', 'status' => 'contacted', 'notes' => 'Warehouse staff, night shift.'],
            ['company_name' => 'Davao Agri Processing Co.', 'contact_name' => 'Grace Lim', 'contact_email' => 'hr@davaoagri.ph', 'contact_phone' => '+639177777771', 'source' => 'event', 'status' => 'qualified', 'notes' => 'Met at Davao jobs fair; 60 packers needed.'],
            ['company_name' => 'Calamba Electronics Corp.', 'contact_name' => 'Kevin Tan', 'contact_email' => 'hrd@calambaelex.ph', 'contact_phone' => '+639178888881', 'source' => 'website', 'status' => 'new', 'notes' => null],
            ['company_name' => 'Ayala Malls Manila Bay', 'contact_name' => 'Sofia Reyes', 'contact_email' => 'admin@ayalamallmb.ph', 'contact_phone' => '+639179999991', 'source' => 'referral', 'status' => 'qualified', 'notes' => 'Housekeeping for mall opening.'],
            ['company_name' => 'Subic Shipyard Services', 'contact_name' => 'Marco Santos', 'contact_email' => 'ops@subicship.ph', 'contact_phone' => '+639170000001', 'source' => 'cold_call', 'status' => 'unqualified', 'notes' => 'No budget until next quarter.'],
        ];
        foreach ($leads as $i => $l) {
            Lead::firstOrCreate(
                ['company_name' => $l['company_name']],
                $l + ['owner_id' => $i % 2 ? $rep2->id : $rep1->id, 'score' => \App\Services\Insights\LeadScorer::score(new Lead($l))]
            );
        }

        $clients = [
            ['name' => 'SM Supermalls – Cebu', 'industry' => 'Retail', 'address_city' => 'Cebu City', 'address_province' => 'Cebu', 'contact_email' => 'hrd@smcebu.ph', 'contact_phone' => '+639174444444', 'status' => 'active', 'source' => 'referral', 'owner' => $rep2, 'contact' => 'Jen Aquino'],
            ['name' => 'Davao Prime Hotel', 'industry' => 'Hospitality', 'address_city' => 'Davao City', 'address_province' => 'Davao del Sur', 'contact_email' => 'admin@davaoprime.ph', 'contact_phone' => '+639175555555', 'status' => 'active', 'source' => 'walk_in', 'owner' => $rep1, 'contact' => 'Mark Villanueva'],
            ['name' => 'Subic Logistics Corp.', 'industry' => 'Logistics', 'address_city' => 'Olongapo City', 'address_province' => 'Zambales', 'contact_email' => 'ops@subiclog.ph', 'contact_phone' => '+639176666666', 'status' => 'prospect', 'source' => 'cold_call', 'owner' => $rep1, 'contact' => 'Carlos Lim'],
            ['name' => 'Makati Medical Center', 'industry' => 'Healthcare', 'address_city' => 'Makati City', 'address_province' => 'Metro Manila', 'contact_email' => 'hr@makatimed.ph', 'contact_phone' => '+639177777777', 'status' => 'inactive', 'source' => 'referral', 'owner' => $rep2, 'contact' => 'Dr. Uy'],
            ['name' => 'Cebu Pacific Catering Services', 'industry' => 'Hospitality', 'address_city' => 'Lapu-Lapu City', 'address_province' => 'Cebu', 'contact_email' => 'hrd@cebucatering.ph', 'contact_phone' => '+639178888882', 'status' => 'inactive', 'source' => 'referral', 'owner' => $rep1, 'contact' => 'Nina Torres'],
            ['name' => 'BDO Unibank Inc.', 'industry' => 'BPO', 'address_city' => 'Makati City', 'address_province' => 'Metro Manila', 'contact_email' => 'procurement@bdo-unibank.ph', 'contact_phone' => '+639179999992', 'status' => 'active', 'source' => 'referral', 'owner' => $rep1, 'contact' => 'Ramon Cruz'],
            ['name' => 'Calamba Electronics Corp.', 'industry' => 'Manufacturing', 'address_city' => 'Calamba', 'address_province' => 'Laguna', 'contact_email' => 'hrd@calambaelex.ph', 'contact_phone' => '+639170000002', 'status' => 'prospect', 'source' => 'website', 'owner' => $rep2, 'contact' => 'Kevin Tan'],
            ['name' => 'Quezon City Retail Group', 'industry' => 'Retail', 'address_city' => 'Quezon City', 'address_province' => 'Metro Manila', 'contact_email' => 'hrd@qcretail.ph', 'contact_phone' => '+639171111112', 'status' => 'prospect', 'source' => 'walk_in', 'owner' => $rep1, 'contact' => 'Liza Fernandez'],
        ];
        foreach ($clients as $c) {
            $owner = $c['owner'];
            unset($c['owner']);
            $contactName = $c['contact'];
            unset($c['contact']);
            $client = Client::firstOrCreate(
                ['name' => $c['name']],
                $c + ['owner_id' => $owner->id, 'last_contacted_at' => $c['status'] === 'inactive' ? now()->subDays(45) : now()->subDays(3)]
            );
            Contact::firstOrCreate(
                ['client_id' => $client->id, 'full_name' => $contactName],
                ['position' => 'HR Manager', 'email' => $client->contact_email, 'phone' => $client->contact_phone, 'is_primary' => true]
            );
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
