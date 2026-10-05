<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Quotations table (spec #4/#5)
|--------------------------------------------------------------------------
| Replaces the mock React state on the Quotations page with a real table.
| A quotation belongs to a lead, is drafted then sent (with a public approve
| link), and moves to approved/declined when the customer responds.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('lead_id', 50)->nullable();
            $table->string('quote_number', 50)->nullable();
            $table->string('client_name', 255);
            $table->string('client_email', 255);
            $table->string('move_type', 255)->nullable();
            $table->string('from_location', 255)->nullable();
            $table->string('to_location', 255)->nullable();
            $table->date('move_date')->nullable();
            // Line items stored as JSON: [{label, qty, unit_price, amount}, ...]
            $table->json('items')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            // draft | sent | approved | declined
            $table->string('status', 50)->default('draft');
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('lead_id', 'quotations_lead_id_idx');
            $table->index('status', 'quotations_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
