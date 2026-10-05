<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'invoice_issued_at')) {
                $table->timestamp('invoice_issued_at')->nullable()->after('quotation_approved_at');
            }
            if (!Schema::hasColumn('leads', 'deposit_paid_at')) {
                $table->timestamp('deposit_paid_at')->nullable()->after('invoice_issued_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['invoice_issued_at', 'deposit_paid_at']);
        });
    }
};
