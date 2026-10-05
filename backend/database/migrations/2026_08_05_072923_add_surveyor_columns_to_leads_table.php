<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('surveyor_name', 255)->nullable()->after('survey_notes');
            $table->string('surveyor_email', 255)->nullable()->after('surveyor_name');
            $table->text('surveyor_report_notes')->nullable()->after('surveyor_email');
            $table->json('surveyor_media')->nullable()->after('surveyor_report_notes');
            $table->timestamp('surveyor_completed_at')->nullable()->after('surveyor_media');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'surveyor_name',
                'surveyor_email',
                'surveyor_report_notes',
                'surveyor_media',
                'surveyor_completed_at',
            ]);
        });
    }
};
