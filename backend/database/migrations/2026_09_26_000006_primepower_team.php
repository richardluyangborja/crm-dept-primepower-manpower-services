<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Single-team rename (follow-up overhaul Phase 1): Primepower Sales → Primepower Team.
        $teamId = DB::table('teams')->where('name', 'Primepower Sales')->value('id')
            ?? DB::table('teams')->where('name', 'Primepower Team')->value('id');
        if (! $teamId) {
            $teamId = DB::table('teams')->insertGetId([
                'name' => 'Primepower Team',
                'region' => 'Nationwide',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('teams')->where('id', $teamId)->update(['name' => 'Primepower Team', 'updated_at' => now()]);
        }
        // Retire legacy regional teams: re-home any stragglers, then remove them from every display.
        DB::table('users')->whereNotNull('team_id')->where('team_id', '!=', $teamId)->update(['team_id' => $teamId]);
        DB::table('users')->whereIn('role', ['superadmin', 'admin'])->update(['team_id' => null]);
        DB::table('teams')->where('id', '!=', $teamId)->delete();
    }

    public function down(): void
    {
        DB::table('teams')->where('name', 'Primepower Team')->update(['name' => 'Primepower Sales', 'updated_at' => now()]);
    }
};
