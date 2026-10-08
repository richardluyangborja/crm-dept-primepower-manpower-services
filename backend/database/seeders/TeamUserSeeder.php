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

        // Demo OTP goes through the real superadmin Gmail account (no placeholder user).
        User::where('email', 'otp.demo@primepower.ph')->delete();
        // Single superadmin: the legacy seed account was merged into Richard Borja
        // (see 2026_10_08 merge migration, which reassigns its rows first).
        // Only drop the legacy row once the keeper exists — never orphan rows
        // on a `db:seed` run where the migration hasn't executed.
        if (User::where('email', 'borja.richard.luyang@gmail.com')->exists()) {
            User::where('email', 'superadmin@primepower.ph')->delete();
        }

        $users = [
            ['name' => 'Richard Borja', 'email' => 'borja.richard.luyang@gmail.com', 'role' => 'superadmin', 'team_id' => null, 'otp_enabled' => true],
            ['name' => 'Admin Ops', 'email' => 'admin@primepower.ph', 'role' => 'admin', 'team_id' => null, 'otp_enabled' => false],
            ['name' => 'Marites Reyes', 'email' => 'manager@primepower.ph', 'role' => 'manager', 'team_id' => $sales->id, 'otp_enabled' => false],
            ['name' => 'Juan Dela Cruz', 'email' => 'rep.juandelacruz@primepower.ph', 'role' => 'sales_rep', 'team_id' => $sales->id, 'phone' => '+639171234567', 'otp_enabled' => false],
            ['name' => 'Maria Santos', 'email' => 'rep.mariasantos@primepower.ph', 'role' => 'sales_rep', 'team_id' => $sales->id, 'phone' => '+639271234567', 'otp_enabled' => false],
        ];
        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                $u + ['password' => env('SEED_PASSWORD', 'Primepower123!'), 'is_active' => true]
            );
            // Team simplification converges on re-seed without touching passwords.
            // OTP stays opt-in: only the real Gmail superadmin has it on; seed
            // emails keep it off (toggled per-user in Settings).
            $user->update(['team_id' => $u['team_id'], 'otp_enabled' => $u['otp_enabled']]);
        }
    }
}
