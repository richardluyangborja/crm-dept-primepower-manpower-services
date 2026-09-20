<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('insight_key', 100);
            $table->string('rating', 4); // up|down
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['insight_key', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_feedback');
    }
};
