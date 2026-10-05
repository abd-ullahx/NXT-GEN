<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (!Schema::hasColumn('emails', 'opened_at')) {
                $table->timestamp('opened_at')->nullable()->after('is_read');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (Schema::hasColumn('emails', 'opened_at')) {
                $table->dropColumn('opened_at');
            }
        });
    }
};
