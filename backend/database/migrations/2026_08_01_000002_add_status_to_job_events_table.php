<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Job status + notes on job_events (spec #10 Jobs module)
|--------------------------------------------------------------------------
| Job cards flow booked -> completed_won or not_completed -> reassigned.
| We store the status directly on the calendar/job event row and index the
| event_date so the Jobs page and calendar can query by day quickly.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->string('status', 50)->default('booked')->after('type');
            $table->text('notes')->nullable()->after('location');

            $table->index('event_date', 'job_events_event_date_idx');
            $table->index('type', 'job_events_type_idx');
            $table->index('status', 'job_events_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->dropIndex('job_events_event_date_idx');
            $table->dropIndex('job_events_type_idx');
            $table->dropIndex('job_events_status_idx');
            $table->dropColumn(['status', 'notes']);
        });
    }
};
