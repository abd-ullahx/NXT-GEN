<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 100);
            $table->string('source', 100);
            $table->string('status', 50)->default('new');
            $table->string('stage', 50)->default('Welcome Email');
            $table->timestamp('welcome_email_sent_at')->nullable();
            $table->timestamp('survey_email_sent_at')->nullable();
            $table->timestamp('survey_requested_at')->nullable();
            $table->timestamp('survey_booked_at')->nullable();
            $table->timestamp('quotation_sent_at')->nullable();
            $table->timestamp('reminder_1_scheduled_at')->nullable();
            $table->timestamp('reminder_2_scheduled_at')->nullable();
            $table->timestamp('reminder_1_sent_at')->nullable();
            $table->timestamp('reminder_2_sent_at')->nullable();
            $table->string('survey_requested_date', 50)->nullable();
            $table->string('survey_requested_time_range', 100)->nullable();
            $table->text('survey_notes')->nullable();
            $table->string('survey_status', 50)->default('pending');
            $table->string('move_type', 255);
            $table->string('from_location', 255);
            $table->string('to_location', 255);
            $table->date('move_date');
            $table->decimal('est_value', 10, 2)->default(0.00);
            $table->integer('ai_score')->default(5);
            $table->string('priority', 50)->default('Warm');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
