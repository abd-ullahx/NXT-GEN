<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance: the Outlook email list orders by received_at and matches inbound
 * replies by from_email. Index both so those queries stay fast as the mailbox grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->index('received_at', 'emails_received_at_idx');
            $table->index('from_email', 'emails_from_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_received_at_idx');
            $table->dropIndex('emails_from_email_idx');
        });
    }
};
