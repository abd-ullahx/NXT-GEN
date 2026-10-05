<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = app('App\Http\Controllers\Api\ChatController');
$res1 = $c->channels()->getContent();
echo "FIRST:\n" . $res1 . "\n";
$res2 = $c->channels()->getContent();
echo "SECOND:\n" . $res2 . "\n";
