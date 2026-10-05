<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| survey_reminders
|--------------------------------------------------------------------------
| Stores the 4 timed pre-survey reminder emails per lead.
| Rows are created (or recreated) when admin approves the survey
| and a survey_requested_date is available on the lead.
|
| Reminder types (fired in order):
|   1_day   — 24 h before survey datetime
|   6_hours — 6 h  before survey datetime
|   1_hour  — 1 h  before survey datetime
|   15_min  — 15 m before survey datetime
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id');
            $table->enum('type', ['1_day', '6_hours', '1_hour', '15_min']);
            $table->string('label', 50);          // "1 Day Before", "6 Hours Before", etc.
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'skipped', 'stopped'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->index('lead_id', 'sr_lead_id_idx');
            $table->index(['status', 'scheduled_at'], 'sr_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_reminders');
    }
};
