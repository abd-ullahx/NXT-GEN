<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\OutlookService::class);
$data = $service->fetchEmails(10);
$emails = $data['value'] ?? [];

echo "TOTAL EMAILS IN OUTLOOK INBOX: " . count($emails) . "\n";

foreach ($emails as $idx => $msg) {
    $from = $msg['from']['emailAddress']['address'] ?? 'unknown';
    $subj = $msg['subject'] ?? 'no subject';
    echo "#" . ($idx + 1) . " From: {$from} | Subject: {$subj}\n";

    $parser = app(App\Services\LeadEmailParser::class);
    $parsed = $parser->parse($msg);
    if ($parsed) {
        echo "   -> PARSED LEAD DATA: " . json_encode($parsed) . "\n";
    } else {
        echo "   -> NOT PARSED AS LEAD\n";
    }
}
