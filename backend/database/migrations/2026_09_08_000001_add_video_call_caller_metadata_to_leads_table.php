<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('video_call_caller_id', 100)->nullable()->after('video_call_token');
            $table->string('video_call_caller_name', 150)->nullable()->after('video_call_caller_id');
            $table->string('video_call_caller_role', 50)->nullable()->after('video_call_caller_name');
            $table->boolean('video_call_is_video')->default(true)->after('video_call_caller_role');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'video_call_caller_id',
                'video_call_caller_name',
                'video_call_caller_role',
                'video_call_is_video',
            ]);
        });
    }
};