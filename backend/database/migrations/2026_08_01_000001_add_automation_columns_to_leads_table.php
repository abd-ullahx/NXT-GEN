<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Automation columns for the lead lifecycle
|--------------------------------------------------------------------------
| Adds the fields the AutomationService needs to drive the draft -> approve
| -> welcome -> quotation -> survey funnel plus the working-hours reminder
| cycle. Everything is nullable so existing rows migrate cleanly.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Admin draft-approval gate (spec #2/#3): leads land as draft and
            // automation only begins once approved_at is set.
            $table->timestamp('approved_at')->nullable()->after('stage');

            // Quotation section state (spec #4/#5).
            $table->string('quotation_status', 50)->nullable()->after('quotation_sent_at');
            $table->timestamp('quotation_approved_at')->nullable()->after('quotation_status');

            // Survey type choice + admin survey approval (spec #6/#7).
            $table->string('survey_type', 50)->nullable()->after('survey_status');
            $table->timestamp('survey_approved_at')->nullable()->after('survey_type');

            // Working-hours reminder engine bookkeeping.
            $table->unsignedTinyInteger('reminder_stage')->default(0)->after('reminder_2_sent_at');
            $table->timestamp('reminder_next_at')->nullable()->after('reminder_stage');
            $table->string('reminder_context', 50)->nullable()->after('reminder_next_at');
            $table->timestamp('last_response_at')->nullable()->after('reminder_context');

            // Indexes for list filtering / reminder sweeps.
            $table->index('created_at', 'leads_created_at_idx');
            $table->index('status', 'leads_status_idx');
            $table->index('stage', 'leads_stage_idx');
            $table->index('reminder_next_at', 'leads_reminder_next_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_created_at_idx');
            $table->dropIndex('leads_status_idx');
            $table->dropIndex('leads_stage_idx');
            $table->dropIndex('leads_reminder_next_at_idx');

            $table->dropColumn([
                'approved_at',
                'quotation_status',
                'quotation_approved_at',
                'survey_type',
                'survey_approved_at',
                'reminder_stage',
                'reminder_next_at',
                'reminder_context',
                'last_response_at',
            ]);
        });
    }
};
