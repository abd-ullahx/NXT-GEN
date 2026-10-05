<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$events = \App\Models\JobEvent::all();
echo "TOTAL EVENTS IN DB: " . $events->count() . "\n";
foreach ($events as $e) {
    echo "EVENT: {$e->id} | {$e->title} | {$e->date} | lead_id: {$e->lead_id}\n";
}
