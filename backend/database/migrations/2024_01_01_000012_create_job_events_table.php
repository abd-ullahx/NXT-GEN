<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_events', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('title', 255);
            $table->string('type', 50)->default('Job');
            $table->date('event_date');
            $table->string('event_time', 50);
            $table->string('lead_id', 50)->nullable();
            $table->string('driver', 100);
            $table->string('vehicle', 255);
            $table->string('location', 255);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_events');
    }
};
