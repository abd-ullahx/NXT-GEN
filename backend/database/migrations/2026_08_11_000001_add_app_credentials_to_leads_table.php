<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('app_user_password', 255)->nullable()->after('survey_approved_at');
            $table->timestamp('credentials_sent_at')->nullable()->after('app_user_password');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['app_user_password', 'credentials_sent_at']);
        });
    }
};
