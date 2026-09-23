<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_province')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });

        foreach (['leads', 'clients', 'opportunities', 'activities', 'followups'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies');
            });
        }

        \App\Services\BackfillCompanies::run();
    }

    public function down(): void
    {
        foreach (['followups', 'activities', 'opportunities', 'clients', 'leads'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
        Schema::dropIfExists('companies');
    }
};
