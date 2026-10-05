<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 100);
            $table->string('type', 50)->default('Customer');
            $table->integer('moves')->default(0);
            $table->decimal('lifetime_value', 10, 2)->default(0.00);
            $table->string('status', 50)->default('Active');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
