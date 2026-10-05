<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Allow long-running audio/survey uploads
set_time_limit(0);

// Look for backend directory in standard cPanel locations
$possibleRoots = [
    __DIR__ . '/../backend',          // Standard secure cPanel: /home/user/backend (outside public_html)
    __DIR__ . '/backend',             // Alternative: /home/user/public_html/backend
    __DIR__ . '/../laravel',          // Alternative: /home/user/laravel
    __DIR__ . '/laravel',             // Alternative: /home/user/public_html/laravel
    __DIR__ . '/..',                  // Alternative: root is parent
];

$backendPath = null;
foreach ($possibleRoots as $candidate) {
    if (file_exists($candidate . '/vendor/autoload.php') && file_exists($candidate . '/bootstrap/app.php')) {
        $backendPath = realpath($candidate);
        break;
    }
}

if (!$backendPath) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Backend Configuration Required</title>';
    echo '<style>body{background:#0b0d12;color:#f3f4f6;font-family:system-ui,-apple-system,sans-serif;padding:40px;line-height:1.6}';
    echo '.card{background:#141720;border:1px solid #c9a84c;border-radius:12px;max-width:650px;margin:40px auto;padding:30px}';
    echo 'h2{color:#d4af37;margin-top:0}code{background:#1c202d;padding:2px 8px;border-radius:4px;color:#d4af37;font-family:monospace}';
    echo '</style></head><body><div class="card">';
    echo '<h2>Next Gen Relocation — Backend Core Not Found</h2>';
    echo '<p>The Laravel backend directory could not be located. Please make sure you uploaded the <code>backend/</code> folder to:</p>';
    echo '<ul><li><code>/home/username/backend/</code> (recommended, outside public_html) or</li>';
    echo '<li><code>/home/username/public_html/backend/</code></li></ul>';
    echo '<p>Also make sure you uploaded the <code>vendor/</code> folder or ran <code>composer install</code> inside the backend folder.</p>';
    echo '<p>Visit <code>/deploy_helper.php</code> in your browser for guided diagnostics.</p>';
    echo '</div></body></html>';
    exit(1);
}

// Maintenance mode check
if (file_exists($maintenance = $backendPath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register autoloader
require $backendPath . '/vendor/autoload.php';

// Bootstrap Laravel
/** @var Application $app */
$app = require_once $backendPath . '/bootstrap/app.php';

// Handle request
$app->handleRequest(Request::capture());
