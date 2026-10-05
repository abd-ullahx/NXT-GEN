<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_videos', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title', 255);
            $table->string('file_name', 255);
            $table->string('video_url', 255);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->default('video/mp4');
            $table->integer('duration_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_videos');
    }
};
