<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name', 255);
            $table->text('desc_text');
            $table->string('category', 100);
            $table->boolean('connected')->default(false);
            $table->string('color', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
