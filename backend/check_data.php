<?php

require __DIR__ . '/vendor/autoload.php';

$capsule = new Illuminate\Database\Capsule\Manager;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'crm',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

use App\Models\Lead;
use App\Models\JobEvent;

echo "=== LEADS ===\n";
$leads = Lead::query()->orderBy('created_at', 'desc')->get();
echo "Total active leads: " . $leads->count() . "\n";
foreach ($leads as $lead) {
    echo "ID: " . $lead->id . ", Name: " . $lead->name . ", Status: " . $lead->status . ", Stage: " . $lead->stage . ", Deleted: " . ($lead->deleted_at ? 'YES' : 'NO') . "\n";
}

echo "\n=== JOB EVENTS ===\n";
$jobs = JobEvent::query()->orderBy('event_date', 'asc')->get();
echo "Total job events: " . $jobs->count() . "\n";
foreach ($jobs as $job) {
    echo "ID: " . $job->id . ", Title: " . $job->title . ", Status: " . $job->status . ", Lead ID: " . $job->lead_id . "\n";
}

echo "\n=== TRASHED LEADS ===\n";
$trashed = Lead::onlyTrashed()->get();
echo "Trashed leads: " . $trashed->count() . "\n";
foreach ($trashed as $lead) {
    echo "ID: " . $lead->id . ", Name: " . $lead->name . ", Status: " . $lead->status . ", Stage: " . $lead->stage . "\n";
}