<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('conversation_id')->nullable();
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->longText('body_html')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->string('lead_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
