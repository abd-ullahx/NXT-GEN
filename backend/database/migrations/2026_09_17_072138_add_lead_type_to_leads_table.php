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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_type')->default('domestic')->after('move_type');
        });

        // Backfill data
        $leads = \Illuminate\Support\Facades\DB::table('leads')->get(['id', 'move_type']);
        foreach ($leads as $lead) {
            $typeStr = strtolower($lead->move_type ?? '');
            $newType = 'domestic';
            
            if (str_contains($typeStr, 'commercial') || str_contains($typeStr, 'office') || str_contains($typeStr, 'business')) {
                $newType = 'commercial';
            } elseif (str_contains($typeStr, 'international') || str_contains($typeStr, 'abroad') || str_contains($typeStr, 'overseas') || str_contains($typeStr, 'country')) {
                $newType = 'international';
            }

            \Illuminate\Support\Facades\DB::table('leads')
                ->where('id', $lead->id)
                ->update(['lead_type' => $newType]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_type');
        });
    }
};
