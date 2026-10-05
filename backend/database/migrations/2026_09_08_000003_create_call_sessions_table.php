<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id', 64)->nullable();
            $table->string('caller_id', 64);
            $table->string('caller_role', 20);
            $table->string('target_role', 20);
            $table->string('target_id', 64)->nullable();
            $table->string('accepted_by', 64)->nullable();
            $table->string('room_id', 128);
            $table->boolean('is_video')->default(false);
            $table->string('call_type', 10)->default('audio');
            $table->string('status', 20)->default('ringing');
            $table->string('end_reason', 64)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->index(['lead_id', 'status'], 'idx_lead_status');
            $table->index(['caller_id', 'status'], 'idx_caller_status');
            $table->index(['created_at', 'status'], 'idx_created_status');
            $table->index(['target_id', 'status'], 'idx_target_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};