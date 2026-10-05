<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('selfie_url', 500)->nullable();
            $table->string('start_meter_url', 500)->nullable();
            $table->string('start_back_url', 500)->nullable();
            $table->decimal('start_odometer', 10, 2)->nullable();
            $table->string('end_meter_url', 500)->nullable();
            $table->string('end_back_url', 500)->nullable();
            $table->decimal('end_odometer', 10, 2)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('fuel_liters', 10, 2)->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('job_started_at')->nullable();
            $table->timestamp('job_completed_at')->nullable();
            $table->string('selfie_url', 500)->nullable();
            $table->string('start_meter_url', 500)->nullable();
            $table->string('start_back_url', 500)->nullable();
            $table->decimal('start_odometer', 10, 2)->nullable();
            $table->string('end_meter_url', 500)->nullable();
            $table->string('end_back_url', 500)->nullable();
            $table->decimal('end_odometer', 10, 2)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('fuel_liters', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->dropColumn([
                'started_at',
                'completed_at',
                'selfie_url',
                'start_meter_url',
                'start_back_url',
                'start_odometer',
                'end_meter_url',
                'end_back_url',
                'end_odometer',
                'distance_km',
                'fuel_liters',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'job_started_at',
                'job_completed_at',
                'selfie_url',
                'start_meter_url',
                'start_back_url',
                'start_odometer',
                'end_meter_url',
                'end_back_url',
                'end_odometer',
                'distance_km',
                'fuel_liters',
            ]);
        });
    }
};
