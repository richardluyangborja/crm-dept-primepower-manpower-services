<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance pass (dashboard + reports ≤5s): indexes behind the trend,
 * forecast, churn, and satisfaction aggregates. All plain btree — portable
 * across pgsql/sqlite/mysql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $t) {
            $t->index('won_at');
            $t->index('lost_at');
            $t->index('created_at');
            $t->index(['owner_id', 'stage']);
        });
        Schema::table('survey_responses', function (Blueprint $t) {
            $t->index('responded_at');
            $t->index('survey_id');
        });
        Schema::table('surveys', function (Blueprint $t) {
            $t->index('created_at');
            $t->index('status');
            $t->index(['client_id', 'created_at']);
            $t->index('sent_by');
        });
        Schema::table('followups', function (Blueprint $t) {
            $t->index(['client_id', 'status']);
        });
        Schema::table('activities', function (Blueprint $t) {
            $t->index(['owner_id', 'occurred_at']);
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->index('last_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $t) {
            $t->dropIndex(['won_at']);
            $t->dropIndex(['lost_at']);
            $t->dropIndex(['created_at']);
            $t->dropIndex(['owner_id', 'stage']);
        });
        Schema::table('survey_responses', function (Blueprint $t) {
            $t->dropIndex(['responded_at']);
            $t->dropIndex(['survey_id']);
        });
        Schema::table('surveys', function (Blueprint $t) {
            $t->dropIndex(['created_at']);
            $t->dropIndex(['status']);
            $t->dropIndex(['client_id', 'created_at']);
            $t->dropIndex(['sent_by']);
        });
        Schema::table('followups', function (Blueprint $t) {
            $t->dropIndex(['client_id', 'status']);
        });
        Schema::table('activities', function (Blueprint $t) {
            $t->dropIndex(['owner_id', 'occurred_at']);
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->dropIndex(['last_contacted_at']);
        });
    }
};
