<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id');
            $table->string('quotation_id');
            $table->string('label', 100);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 50)->default('pending'); // pending | sent | skipped | stopped
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->index('lead_id', 'qr_lead_id_idx');
            $table->index('quotation_id', 'qr_quote_id_idx');
            $table->index(['status', 'scheduled_at'], 'qr_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_reminders');
    }
};
