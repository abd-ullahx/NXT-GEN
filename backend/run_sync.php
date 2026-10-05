<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\OutlookService::class);
try {
    $count = $service->syncEmails(20);
    echo "SYNC_SUCCESS: " . $count . " new email(s) synced.\n";
    $allLeads = \App\Models\Lead::all();
    echo "ALL_LEADS_COUNT: " . $allLeads->count() . "\n";
    foreach ($allLeads as $l) {
        echo "LEAD: " . json_encode($l) . "\n";
    }
} catch (\Exception $e) {
    echo "SYNC_ERROR: " . $e->getMessage() . "\n";
}
