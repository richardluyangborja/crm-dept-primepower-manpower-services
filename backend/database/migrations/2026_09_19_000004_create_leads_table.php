<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('converted_client_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
