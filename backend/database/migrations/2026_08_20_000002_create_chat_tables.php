<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 255);
            $table->string('description', 255)->nullable();
            $table->boolean('is_private')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->text('message');
            $table->string('attachment_url', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['channel_id', 'created_at']);
            $table->index(['sender_id', 'receiver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_channels');
    }
};
