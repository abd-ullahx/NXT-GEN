<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->date('transaction_date');
            $table->string('label', 255);
            $table->string('category', 100);
            $table->string('type', 50)->default('income');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
