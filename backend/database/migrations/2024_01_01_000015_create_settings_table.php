<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('business_name', 255)->default('NEXT GEN RELOCATION LTD');
            $table->string('trading_region', 255)->default('Slough & Home Counties');
            $table->string('contact_email', 255)->default('hello@nextgenrelocation.co.uk');
            $table->string('phone', 100)->default('+44 1753 555 200');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
