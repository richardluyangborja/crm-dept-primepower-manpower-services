<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('nps');
            $table->json('questions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('survey_templates');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users');
            $table->string('channel')->default('link');
            $table->string('token', 64)->unique();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('answers')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('responded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('survey_templates');
    }
};
