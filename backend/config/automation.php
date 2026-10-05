<?php

/*
|--------------------------------------------------------------------------
| Lead Automation & Reminder Engine Configuration
|--------------------------------------------------------------------------
|
| Controls the automated email lifecycle: welcome -> quotation -> survey,
| and the working-hours reminder cycle that follows up with customers who
| have not yet responded to the quotation or survey emails.
|
| All timing is evaluated in a single company timezone (business_timezone).
| Reminders are only ever sent inside the working-hours window and are
| skipped the moment the customer responds (clicks an approve/booking link
| or replies by email).
|
*/

return [

    // The single business timezone that defines "working hours". Every
    // reminder slot is computed in this zone regardless of server timezone.
    'business_timezone' => env('APP_BUSINESS_TIMEZONE', 'Europe/London'),

    // Working-hours window (24h clock, inclusive start, exclusive end).
    // Reminders only fire when the local business time is within this range.
    'work_start_hour' => (int) env('WORK_START_HOUR', 9),   // 09:00
    'work_end_hour'   => (int) env('WORK_END_HOUR', 18),    // 18:00

    // Gap between successive follow-up reminders, in minutes. Exactly 120 minutes (2 hours).
    'reminder_interval_minutes' => (int) env('REMINDER_INTERVAL_MINUTES', 120),
    'reminder_jitter_minutes'   => (int) env('REMINDER_JITTER_MINUTES', 0),

    // Maximum number of reminder cycles before we give up (spec: 3-4 times).
    'reminder_max_cycles' => (int) env('REMINDER_MAX_CYCLES', 4),

    // Public base URL used to build approve / booking links inside emails.
    'public_base_url' => env('PUBLIC_BASE_URL', env('APP_URL', 'http://localhost:8000')),
];
