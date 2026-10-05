<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('attachment_name', 255)->nullable()->after('attachment_url');
            $table->string('attachment_mime', 150)->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_mime']);
        });
    }
};