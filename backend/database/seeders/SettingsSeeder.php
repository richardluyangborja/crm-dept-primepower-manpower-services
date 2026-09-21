<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Step 6 (specs/09 + 12): org defaults, master-data lists, personal preferences. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'org_name' => 'PrimePower Manpower Services',
            'timezone' => 'Asia/Manila',
            'currency' => '₱',
            'date_format' => 'M d, Y',
            'language' => 'en-PH',
            'appearance_default' => 'system',
            'report_schedule' => 'monthly',
            'retention_days' => 90,
            'industries' => ['BPO', 'Manufacturing', 'Hospitality', 'Retail', 'Healthcare', 'Logistics'],
            'lead_sources' => ['facebook', 'gmail', 'phone', 'referral', 'walk_in', 'website', 'cold_call', 'event'],
            'pipeline_stages' => [
                ['key' => 'new', 'label' => 'Inquiry'],
                ['key' => 'contacted', 'label' => 'Contacted'],
                ['key' => 'qualified', 'label' => 'Qualified'],
                ['key' => 'proposal', 'label' => 'Quotation'],
                ['key' => 'negotiation', 'label' => 'Approval'],
                ['key' => 'contract', 'label' => 'Contract'],
                ['key' => 'won', 'label' => 'Won'],
                ['key' => 'lost', 'label' => 'Lost'],
            ],
            'lost_reasons' => ['Chose competitor pricing', 'No response after quotation', 'Budget frozen', 'Timing'],
        ];
        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $prefs = [
            'rep.juandelacruz@primepower.ph' => ['theme' => 'dark', 'sync_system' => true],
            'rep.mariasantos@primepower.ph' => ['theme' => 'dark', 'sync_system' => true],
            'manager@primepower.ph' => ['theme' => 'light', 'sync_system' => false],
        ];
        foreach ($prefs as $email => $partial) {
            $user = User::where('email', $email)->first();
            if (! $user) continue;
            $base = [
                'theme' => 'system', 'sync_system' => true,
                'notifications' => ['reminder_due' => true, 'overdue' => true, 'escalation' => true, 'survey_response' => true, 'assignment' => true],
                'quiet_hours_start' => null, 'quiet_hours_end' => null,
            ];
            $user->update(['preferences' => array_merge($base, $user->preferences ?? [], $partial)]);
        }
    }
}
