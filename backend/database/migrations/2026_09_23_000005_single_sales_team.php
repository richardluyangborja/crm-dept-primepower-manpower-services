<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Single-team simplification (specs/02 + 09): one Primepower Sales team.
        $teamId = DB::table('teams')->insertGetId([
            'name' => 'Primepower Sales',
            'region' => 'Nationwide',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Sales roles converge onto it; admins step out of teams entirely.
        DB::table('users')->whereIn('role', ['manager', 'sales_rep'])->update(['team_id' => $teamId]);
        DB::table('users')->whereIn('role', ['superadmin', 'admin'])->update(['team_id' => null]);
    }

    public function down(): void
    {
        // Cannot restore previous assignments; detach members, then drop the team.
        $id = DB::table('teams')->where('name', 'Primepower Sales')->value('id');
        if ($id) {
            DB::table('users')->where('team_id', $id)->update(['team_id' => null]);
            DB::table('teams')->where('id', $id)->delete();
        }
    }
};
