<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-class mock invoices (Phase 2B): winning persists the Dept-5 mock
     * draft here so AR aging, payments, and collection follow-ups are visible
     * and reconcilable. Mock-sourced — no live finance integration.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('ref')->unique(); // e.g. INV-2026-0004 (mock Dept 5)
            $table->string('title');
            $table->unsignedBigInteger('amount_centavos');
            $table->unsignedBigInteger('balance_centavos');
            $table->string('status')->default('draft'); // draft → sent → paid | overdue
            $table->date('due_at')->nullable();
            $table->json('payload')->nullable(); // raw mock snapshot for auditability
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
