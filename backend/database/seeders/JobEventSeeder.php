<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobEventSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally left empty.
        // Job events are created automatically via the Lead lifecycle (LeadController@store, LeadController@updateStatus).
        // Do NOT add DB::table('job_events')->delete() here — it causes data loss.
    }
}
