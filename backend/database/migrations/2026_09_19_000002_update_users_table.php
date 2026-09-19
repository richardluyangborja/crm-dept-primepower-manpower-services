<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('sales_rep')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->boolean('otp_enabled')->default(false)->after('phone');
            $table->json('preferences')->nullable()->after('otp_enabled');
            $table->boolean('is_active')->default(true)->after('preferences');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['role', 'phone', 'otp_enabled', 'preferences', 'is_active', 'last_login_at']);
        });
    }
};
