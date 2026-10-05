<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('indicative_quote_range', 100)->nullable();
            $table->text('scope_summary')->nullable();
            $table->text('missing_information_summary')->nullable();
            $table->text('special_item_prompt')->nullable();
            $table->string('survey_deadline', 50)->nullable();
            $table->string('recommended_survey_type', 50)->nullable();
            $table->text('quoted_services')->nullable();
            $table->string('quote_valid_until', 50)->nullable();
            $table->string('deposit_amount_or_percent', 100)->nullable();
            $table->text('deposit_instruction')->nullable();
            $table->string('quote_version', 50)->nullable();
            $table->string('arrival_window', 100)->nullable();
            $table->string('job_lead_name', 255)->nullable();
            $table->string('payment_status', 50)->nullable();
            $table->string('coordinator_name', 255)->nullable();
            $table->string('completion_date', 50)->nullable();
            $table->string('review_platform_name', 100)->nullable();
            $table->string('review_date', 50)->nullable();
            $table->string('referral_code', 100)->nullable();
            $table->string('referral_offer', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'indicative_quote_range',
                'scope_summary',
                'missing_information_summary',
                'special_item_prompt',
                'survey_deadline',
                'recommended_survey_type',
                'quoted_services',
                'quote_valid_until',
                'deposit_amount_or_percent',
                'deposit_instruction',
                'quote_version',
                'arrival_window',
                'job_lead_name',
                'payment_status',
                'coordinator_name',
                'completion_date',
                'review_platform_name',
                'review_date',
                'referral_code',
                'referral_offer',
            ]);
        });
    }
};
