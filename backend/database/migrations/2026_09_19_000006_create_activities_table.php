<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
