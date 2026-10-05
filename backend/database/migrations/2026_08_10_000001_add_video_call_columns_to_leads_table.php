<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('video_call_room_id', 255)->nullable()->after('survey_type');
            $table->string('video_call_status', 50)->default('idle')->after('video_call_room_id'); // idle | ringing | in_progress | ended | missed
            $table->string('video_call_token', 255)->nullable()->after('video_call_status');
            $table->timestamp('video_call_started_at')->nullable()->after('video_call_token');
            $table->timestamp('video_call_ended_at')->nullable()->after('video_call_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'video_call_room_id',
                'video_call_status',
                'video_call_token',
                'video_call_started_at',
                'video_call_ended_at',
            ]);
        });
    }
};
