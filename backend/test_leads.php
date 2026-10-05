<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$leads = \App\Models\Lead::all()->toArray();
echo json_encode($leads, JSON_PRETTY_PRINT);
