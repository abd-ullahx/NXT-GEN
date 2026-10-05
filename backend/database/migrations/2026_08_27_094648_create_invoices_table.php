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
        Schema::create('invoices', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_id');
            $table->string('quotation_id')->nullable();
            $table->string('invoice_number');
            $table->string('client_name');
            $table->string('client_email');
            $table->string('service_title')->default('Relocation Service');
            $table->string('status')->default('Draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->json('items');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
