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
        // Add quotation workflow & deposit fields
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'deposit_percent')) {
                $table->decimal('deposit_percent', 5, 2)->default(20.00)->after('total');
            }
            if (!Schema::hasColumn('quotations', 'deposit_amount')) {
                $table->decimal('deposit_amount', 10, 2)->default(0.00)->after('deposit_percent');
            }
            if (!Schema::hasColumn('quotations', 'payment_option')) {
                $table->string('payment_option')->nullable()->after('deposit_amount'); // 'pay_later', 'pay_now'
            }
            if (!Schema::hasColumn('quotations', 'initial_deposit_paid')) {
                $table->boolean('initial_deposit_paid')->default(false)->after('payment_option');
            }
        });

        // Add payment and multi-stage deposit fields to invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'invoice_type')) {
                $table->string('invoice_type')->default('standard')->after('service_title'); // 'initial_deposit', 'plan_deposit', 'final_balance', 'standard'
            }
            if (!Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status'); // 'stripe', 'bank_transfer', 'cash'
            }
            if (!Schema::hasColumn('invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0.00)->after('total');
            }
            if (!Schema::hasColumn('invoices', 'balance_due')) {
                $table->decimal('balance_due', 10, 2)->default(0.00)->after('paid_amount');
            }
            if (!Schema::hasColumn('invoices', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('balance_due');
            }
            if (!Schema::hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_reference');
            }
        });

        // Add deposit tracking fields to leads
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'initial_deposit_paid')) {
                $table->boolean('initial_deposit_paid')->default(false)->after('payment_status');
            }
            if (!Schema::hasColumn('leads', 'total_deposit_paid')) {
                $table->decimal('total_deposit_paid', 10, 2)->default(0.00)->after('initial_deposit_paid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['deposit_percent', 'deposit_amount', 'payment_option', 'initial_deposit_paid']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_type', 'payment_method', 'paid_amount', 'balance_due', 'payment_reference', 'paid_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['initial_deposit_paid', 'total_deposit_paid']);
        });
    }
};
