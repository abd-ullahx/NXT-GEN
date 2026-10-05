<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Simulate request
$request = \Illuminate\Http\Request::create('/api/leads', 'GET');
$controller = $app->make(\App\Http\Controllers\Api\LeadController::class);
$response = $controller->index($request);
echo $response->getContent();
