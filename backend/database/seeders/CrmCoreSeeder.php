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
    }
}
