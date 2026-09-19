<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('size_band')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_province')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('status')->default('prospect');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('created_from_lead_id')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('clients');
    }
};
