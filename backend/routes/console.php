<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync Outlook every minute so provider leads appear without manual sync.
Schedule::command('outlook:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Advance the working-hours reminder cycle (quotation / survey follow-ups).
// The command itself only sends inside working hours, so a tight cadence is
// safe and lets short REMINDER_INTERVAL_MINUTES demo overrides fire promptly.
Schedule::command('automation:run-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

