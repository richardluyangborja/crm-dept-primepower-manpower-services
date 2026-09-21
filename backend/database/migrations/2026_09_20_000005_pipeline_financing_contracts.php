<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->unsignedInteger('headcount')->nullable()->after('value_centavos');
            $table->unsignedBigInteger('rate_per_head_centavos')->nullable()->after('headcount');
            $table->unsignedInteger('contract_months')->nullable()->after('rate_per_head_centavos');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedInteger('headcount_needed')->nullable()->after('contact_phone');
            $table->string('positions', 500)->nullable()->after('headcount_needed');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            // Snapshot of agreed terms (mock Depts: Core-3 docs, Governance legal, Facilities contracts).
            $table->unsignedInteger('headcount');
            $table->unsignedBigInteger('rate_per_head_centavos');
            $table->unsignedInteger('contract_months');
            $table->unsignedBigInteger('monthly_billing_centavos');
            $table->unsignedBigInteger('contract_total_centavos');
            $table->date('start_date');
            $table->string('ref')->unique(); // e.g. CTR-2026-0004 (mock)
            $table->string('status')->default('active'); // active | superseded | cancelled
            $table->json('payload')->nullable(); // raw mock snapshot for auditability
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['headcount', 'rate_per_head_centavos', 'contract_months']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['headcount_needed', 'positions']);
        });
        Schema::dropIfExists('contracts');
    }
};
