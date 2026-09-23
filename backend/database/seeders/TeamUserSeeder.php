<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamUserSeeder extends Seeder
{
    public function run(): void
    {
        $manila = Team::firstOrCreate(['name' => 'Manila'], ['region' => 'NCR']);
        $cebu = Team::firstOrCreate(['name' => 'Cebu'], ['region' => 'Central Visayas']);
        Team::firstOrCreate(['name' => 'Davao'], ['region' => 'Davao Region']);

        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@primepower.ph', 'role' => 'superadmin', 'team_id' => $manila->id],
            ['name' => 'Admin Ops', 'email' => 'admin@primepower.ph', 'role' => 'admin', 'team_id' => $manila->id],
            ['name' => 'Marites Reyes', 'email' => 'manager@primepower.ph', 'role' => 'manager', 'team_id' => $manila->id],
            ['name' => 'Juan Dela Cruz', 'email' => 'rep.juandelacruz@primepower.ph', 'role' => 'sales_rep', 'team_id' => $manila->id, 'phone' => '+639171234567'],
            ['name' => 'Maria Santos', 'email' => 'rep.mariasantos@primepower.ph', 'role' => 'sales_rep', 'team_id' => $cebu->id, 'phone' => '+639271234567'],
            ['name' => 'OTP Demo', 'email' => 'otp.demo@primepower.ph', 'role' => 'sales_rep', 'team_id' => $manila->id, 'phone' => '+639451234567', 'otp_enabled' => true],
        ];
        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                $u + ['password' => env('SEED_PASSWORD', 'Primepower123!'), 'is_active' => true]
            );
        }
    }
}
