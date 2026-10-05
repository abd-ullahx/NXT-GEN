<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds structured survey report fields to the leads table.
 * These fields are submitted by the Flutter surveyor app via:
 *   POST /api/leads/{id}/submit-survey-report
 *
 * Flutter Guide fields breakdown:
 *   Section C: House Shift & Cargo Details
 *   Section D: Optional Access Requirements (with dynamic custom fields)
 *   Section E: Furniture Dismantling & Reassembly (with dynamic custom items)
 *   Section F: Fragile & High-Value Items (with dynamic custom fields)
 *   Section G: Text Notes & Findings
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // ── Section C: House Shift & Cargo ──
            $table->string('property_type')->nullable()->after('surveyor_report_notes');       // e.g. "3 BHK Apartment", "Villa"
            $table->string('cargo_volume')->nullable()->after('property_type');               // e.g. "1,250 Cu. Ft."
            $table->string('total_boxes')->nullable()->after('cargo_volume');                 // e.g. "35 Boxes / Items"
            $table->string('recommended_vehicle')->nullable()->after('total_boxes');          // e.g. "2 Luton Vans (3.5T)"
            $table->string('packing_service')->nullable()->after('recommended_vehicle');      // e.g. "Full Packing + Fragile Wrapping"
            $table->string('access_origin')->nullable()->after('packing_service');           // e.g. "2nd Floor (Elevator Available)"

            // ── Section D: Access Requirements ──
            $table->boolean('lift_available')->nullable()->after('access_origin');           // true/false/null
            $table->boolean('parking_available')->nullable()->after('lift_available');       // true/false/null
            $table->json('custom_access_fields')->nullable()->after('parking_available');
            // JSON: [{"label": "Narrow Staircase", "value": "Yes"}, ...]

            // ── Section E: Dismantling ──
            $table->boolean('requires_disassembly')->nullable()->after('custom_access_fields');
            $table->integer('beds_quantity')->nullable()->after('requires_disassembly');
            $table->integer('wardrobes_quantity')->nullable()->after('beds_quantity');
            $table->json('custom_dismantle_items')->nullable()->after('wardrobes_quantity');
            // JSON: [{"name": "Dining Table", "quantity": 1}, ...]

            // ── Section F: Fragile Items ──
            $table->boolean('has_fragile_items')->nullable()->after('custom_dismantle_items');
            $table->boolean('fine_art_paintings')->nullable()->after('has_fragile_items');
            $table->boolean('piano_antique_items')->nullable()->after('fine_art_paintings');
            $table->json('custom_fragile_fields')->nullable()->after('piano_antique_items');
            // JSON: [{"label": "Crystal Chandelier", "value": "Yes"}, ...]

            // ── Section G: Text Notes ──
            $table->text('special_instructions')->nullable()->after('custom_fragile_fields');
            $table->text('surveyor_findings')->nullable()->after('special_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'property_type', 'cargo_volume', 'total_boxes', 'recommended_vehicle',
                'packing_service', 'access_origin', 'lift_available', 'parking_available',
                'custom_access_fields', 'requires_disassembly', 'beds_quantity',
                'wardrobes_quantity', 'custom_dismantle_items', 'has_fragile_items',
                'fine_art_paintings', 'piano_antique_items', 'custom_fragile_fields',
                'special_instructions', 'surveyor_findings',
            ]);
        });
    }
};
