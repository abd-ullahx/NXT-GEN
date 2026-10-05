<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_media', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id', 50)->index(); // "L-8855" or numeric
            $table->string('type', 20);             // 'image' | 'video' | 'note'
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('surveyor_name', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_media');
    }
};
