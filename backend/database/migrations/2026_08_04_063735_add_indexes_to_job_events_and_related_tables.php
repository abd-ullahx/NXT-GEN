<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->index('lead_id', 'job_events_lead_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_events', function (Blueprint $table) {
            $table->dropIndex('job_events_lead_id_idx');
        });
    }
};


