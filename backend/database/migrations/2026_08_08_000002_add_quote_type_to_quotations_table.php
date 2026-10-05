<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Add quote_type to quotations
|--------------------------------------------------------------------------
| Distinguishes the two automatic quotation types:
|   indicative  — created when lead is approved (pre-survey estimate)
|   post-survey — created after surveyor submits their report (final price)
| Manual quotes created by admin have quote_type = null.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('quote_type', 30)->nullable()->after('lead_id')
                  ->comment('indicative | post-survey | null (manual)');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('quote_type');
        });
    }
};
