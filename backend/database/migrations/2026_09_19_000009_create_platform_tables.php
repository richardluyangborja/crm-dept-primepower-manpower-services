<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose')->default('login');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jti')->unique();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('insights_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('insights_cache');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('otps');
        Schema::dropIfExists('audit_logs');
    }
};
