<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('driver_id')->nullable()->after('surveyor_completed_at');
            $table->string('driver_name')->nullable()->after('driver_id');
            $table->string('driver_email')->nullable()->after('driver_name');
            $table->string('driver_phone')->nullable()->after('driver_email');
            $table->timestamp('driver_assigned_at')->nullable()->after('driver_phone');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'driver_id',
                'driver_name',
                'driver_email',
                'driver_phone',
                'driver_assigned_at',
            ]);
        });
    }
};
