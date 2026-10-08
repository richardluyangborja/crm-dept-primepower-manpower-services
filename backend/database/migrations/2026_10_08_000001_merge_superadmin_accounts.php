<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-superadmin merge: everything owned by the legacy
 * superadmin@primepower.ph moves to borja.richard.luyang@gmail.com,
 * then the legacy row is removed. Safe to re-run (no-ops when done).
 */
return new class extends Migration
{
    public const LEGACY = 'superadmin@primepower.ph';

    public const KEEPER = 'borja.richard.luyang@gmail.com';

    /** [table => columns] holding a users.id reference. */
    protected function userColumns(): array
    {
        return [
            'companies' => ['owner_id'],
            'leads' => ['owner_id'],
            'clients' => ['owner_id'],
            'opportunities' => ['owner_id'],
            'contracts' => ['owner_id'],
            'job_orders' => ['owner_id'],
            'invoices' => ['owner_id'],
            'followups' => ['owner_id'],
            'activities' => ['owner_id'],
            'surveys' => ['sent_by'],
            'survey_templates' => ['created_by'],
            'insight_feedback' => ['user_id'],
            'otps' => ['user_id'],
            'user_sessions' => ['user_id'],
            'notifications' => ['user_id'],
            'audit_logs' => ['user_id'],
        ];
    }

    public function up(): void
    {
        $keeper = User::where('email', self::KEEPER)->first();
        if (! $keeper) {
            $keeper = User::create([
                'name' => 'Richard Borja',
                'email' => self::KEEPER,
                'password' => env('SEED_PASSWORD', 'Primepower123!'),
                'role' => 'superadmin',
                'team_id' => null,
                'otp_enabled' => true,
                'is_active' => true,
            ]);
        } else {
            $keeper->update([
                'name' => 'Richard Borja',
                'role' => 'superadmin',
                'team_id' => null,
                'otp_enabled' => true,
                'is_active' => true,
            ]);
        }

        $legacy = User::where('email', self::LEGACY)->first();
        if (! $legacy || $legacy->id === $keeper->id) {
            return;
        }

        foreach ($this->userColumns() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::table($table)->where($column, $legacy->id)->update([$column => $keeper->id]);
            }
        }

        // Successor for deactivation handover must be sales staff — the keeper
        // is not eligible, so legacy rows move above; nothing should remain.
        $legacy->delete();
    }

    public function down(): void
    {
        // One-way merge by design: the legacy account is not recreated.
    }
};
