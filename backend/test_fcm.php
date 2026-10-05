<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('name', 'manan')->whereNotNull('fcm_token')->first();

if (!$user) {
    echo "User 'manan' with FCM token not found.\n";
    exit(1);
}

$token = $user->fcm_token;
$fcmService = app(\App\Services\FcmService::class);

$title = "Test Notification 🚀";
$body = "Hello from the Laravel Backend! If you see this, push notifications are working perfectly.";
$data = [
    'type' => 'test_push',
    'screen' => 'home'
];

echo "Sending test push to token: " . substr($token, 0, 20) . "...\n";

$result = $fcmService->sendToDevice($token, $title, $body, $data);

if ($result) {
    echo "SUCCESS: Push notification sent!\n";
} else {
    echo "FAILED: Could not send push notification.\n";
}
