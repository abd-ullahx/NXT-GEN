<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            if (!Schema::hasColumn('job_events', 'client_job_status')) {
                $table->string('client_job_status')->default('pending')->nullable();
            }
            if (!Schema::hasColumn('job_events', 'driver_job_status')) {
                $table->string('driver_job_status')->default('pending')->nullable();
            }
            if (!Schema::hasColumn('job_events', 'job_reschedule_reason')) {
                $table->text('job_reschedule_reason')->nullable();
            }
            if (!Schema::hasColumn('job_events', 'job_proposed_date')) {
                $table->string('job_proposed_date')->nullable();
            }
            if (!Schema::hasColumn('job_events', 'job_proposed_time')) {
                $table->string('job_proposed_time')->nullable();
            }
            if (!Schema::hasColumn('job_events', 'job_schedule_token')) {
                $table->string('job_schedule_token')->nullable();
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'client_job_status')) {
                $table->string('client_job_status')->default('pending')->nullable();
            }
            if (!Schema::hasColumn('leads', 'driver_job_status')) {
                $table->string('driver_job_status')->default('pending')->nullable();
            }
            if (!Schema::hasColumn('leads', 'job_reschedule_reason')) {
                $table->text('job_reschedule_reason')->nullable();
            }
            if (!Schema::hasColumn('leads', 'job_proposed_date')) {
                $table->string('job_proposed_date')->nullable();
            }
            if (!Schema::hasColumn('leads', 'job_proposed_time')) {
                $table->string('job_proposed_time')->nullable();
            }
            if (!Schema::hasColumn('leads', 'job_schedule_token')) {
                $table->string('job_schedule_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->dropColumn([
                'client_job_status',
                'driver_job_status',
                'job_reschedule_reason',
                'job_proposed_date',
                'job_proposed_time',
                'job_schedule_token',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'client_job_status',
                'driver_job_status',
                'job_reschedule_reason',
                'job_proposed_date',
                'job_proposed_time',
                'job_schedule_token',
            ]);
        });
    }
};
