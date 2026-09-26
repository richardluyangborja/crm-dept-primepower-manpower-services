<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamUserSeeder extends Seeder
{
    public function run(): void
    {
        // Single team (specs/02 + follow-up overhaul Phase 1): the whole salesforce lives here.
        $sales = Team::firstOrCreate(['name' => 'Primepower Team'], ['region' => 'Nationwide']);

        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@primepower.ph', 'role' => 'superadmin', 'team_id' => null],
            ['name' => 'Admin Ops', 'email' => 'admin@primepower.ph', 'role' => 'admin', 'team_id' => null],
            ['name' => 'Marites Reyes', 'email' => 'manager@primepower.ph', 'role' => 'manager', 'team_id' => $sales->id],
            ['name' => 'Juan Dela Cruz', 'email' => 'rep.juandelacruz@primepower.ph', 'role' => 'sales_rep', 'team_id' => $sales->id, 'phone' => '+639171234567'],
            ['name' => 'Maria Santos', 'email' => 'rep.mariasantos@primepower.ph', 'role' => 'sales_rep', 'team_id' => $sales->id, 'phone' => '+639271234567'],
            ['name' => 'OTP Demo', 'email' => 'otp.demo@primepower.ph', 'role' => 'sales_rep', 'team_id' => $sales->id, 'phone' => '+639451234567', 'otp_enabled' => true],
        ];
        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                $u + ['password' => env('SEED_PASSWORD', 'Primepower123!'), 'is_active' => true]
            );
            // Team simplification converges on re-seed without touching passwords.
            $user->update(['team_id' => $u['team_id']]);
        }
    }
}
