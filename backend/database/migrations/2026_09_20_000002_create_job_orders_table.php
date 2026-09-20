<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-class mock Job Orders (specs/18 §3A): winning an opportunity
     * persists the Dept-1 mock payload here so the client timeline can show
     * staffing → deployment → billing as a visible journey. Mock-sourced,
     * like everything else — no live integration.
     */
    public function up(): void
    {
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('ref')->unique(); // e.g. JO-2026-0004 (mock Dept 1)
            $table->string('title');
            $table->unsignedInteger('headcount')->nullable(); // null = estimating
            $table->unsignedBigInteger('value_centavos')->default(0);
            $table->string('status')->default('draft'); // draft → staffed → deployed → billed
            $table->string('invoice_ref')->nullable(); // mock Dept 5 draft
            $table->json('payload')->nullable(); // raw mock snapshot for auditability
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};
