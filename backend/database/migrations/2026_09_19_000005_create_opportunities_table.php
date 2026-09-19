<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('title');
            $table->string('stage')->default('new');
            $table->unsignedBigInteger('value_centavos')->default(0);
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['stage', 'expected_close_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
