<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->string('contact_id', 50)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_call_sid', 100)->unique();
            $table->string('direction', 20)->default('outbound'); // inbound | outbound
            $table->string('from_number', 50)->nullable();
            $table->string('to_number', 50)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable()->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('recording_url')->nullable();
            $table->string('recording_path', 500)->nullable();
            $table->longText('transcript')->nullable();
            $table->string('transcript_language', 20)->nullable();
            $table->enum('transcript_status', ['pending', 'processing', 'done', 'failed'])->default('pending')->index();
            $table->text('transcript_error')->nullable();
            $table->string('join_token', 100)->nullable()->unique();
            $table->timestamp('join_token_expires_at')->nullable();
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
