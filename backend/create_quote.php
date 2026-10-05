<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$q = \App\Models\Quotation::first()->replicate();
$q->id = 'Q-TEST02';
$q->status = 'sent';
$q->save();
echo $q->id;
