<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$leads = App\Models\Lead::select('id', 'name', 'email', 'video_call_status', 'video_call_room_id')->get();
echo json_encode($leads, JSON_PRETTY_PRINT);
