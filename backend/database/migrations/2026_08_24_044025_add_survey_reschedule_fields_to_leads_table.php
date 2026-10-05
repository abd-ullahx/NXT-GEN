<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('reschedule_proposed_date', 50)->nullable()->after('survey_notes');
            $table->string('reschedule_proposed_time', 50)->nullable()->after('reschedule_proposed_date');
            $table->string('reschedule_status', 50)->default('none')->after('reschedule_proposed_time'); // none | proposed | client_approved | declined
            $table->string('reschedule_token', 100)->nullable()->unique()->after('reschedule_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'reschedule_proposed_date',
                'reschedule_proposed_time',
                'reschedule_status',
                'reschedule_token',
            ]);
        });
    }
};
