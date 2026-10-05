<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally left empty.
        // Leads are created via the public website form (LeadController@store) or manually by admin.
        // Do NOT add DB::table('leads')->delete() here — it causes data loss.
    }
}
