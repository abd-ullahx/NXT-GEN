<?php
/**
 * Next Gen Relocation — cPanel Deployment & Diagnostic Helper
 * 
 * Usage:
 * Upload this file to public_html/deploy_helper.php on your cPanel.
 * Access in browser: https://yourdomain.com/deploy_helper.php
 * For security, set a SETUP_KEY below or in your backend .env file.
 */

// Define access password (change this or set SETUP_KEY in backend/.env)
$SETUP_KEY = 'nextgen2026';

session_start();

$possibleRoots = [
    __DIR__ . '/../backend',
    __DIR__ . '/backend',
    __DIR__ . '/../laravel',
    __DIR__ . '/laravel',
];

$backendPath = null;
foreach ($possibleRoots as $candidate) {
    if (file_exists($candidate . '/bootstrap/app.php')) {
        $backendPath = realpath($candidate);
        break;
    }
}

// Check authorization
$authenticated = false;
if (isset($_POST['setup_key']) && $_POST['setup_key'] === $SETUP_KEY) {
    $_SESSION['auth_setup'] = true;
}
if (!empty($_SESSION['auth_setup'])) {
    $authenticated = true;
}

$actionOutput = '';
$actionStatus = '';

if ($authenticated && isset($_POST['action']) && $backendPath) {
    $action = $_POST['action'];

    if ($action === 'storage_link') {
        $target = $backendPath . '/storage/app/public';
        $link = __DIR__ . '/storage';
        if (file_exists($link)) {
            $actionOutput = "Storage link or folder already exists at: {$link}";
            $actionStatus = "warning";
        } else {
            if (!file_exists($target)) {
                mkdir($target, 0755, true);
            }
            if (@symlink($target, $link)) {
                $actionOutput = "Successfully created symlink from {$link} -> {$target}";
                $actionStatus = "success";
            } else {
                $actionOutput = "Failed to create symlink (symlink function may be disabled by your hosting provider). You can create a directory named 'storage' in public_html and copy contents manually.";
                $actionStatus = "error";
            }
        }
    } elseif ($action === 'migrate' || $action === 'cache_clear' || $action === 'optimize') {
        require_once $backendPath . '/vendor/autoload.php';
        $app = require_once $backendPath . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

        ob_start();
        try {
            if ($action === 'migrate') {
                $kernel->call('migrate', ['--force' => true]);
            } elseif ($action === 'cache_clear') {
                $kernel->call('optimize:clear');
            } elseif ($action === 'optimize') {
                $kernel->call('config:cache');
                $kernel->call('route:cache');
            }
            $actionOutput = $kernel->output() ?: 'Command executed successfully!';
            $actionStatus = 'success';
        } catch (\Throwable $e) {
            $actionOutput = "Error: " . $e->getMessage();
            $actionStatus = 'error';
        }
        ob_end_clean();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cPanel Deployment Helper | Next Gen Relocation</title>
    <style>
        * { box-sizing: border-box; }
        body { background: #0a0c10; color: #e5e7eb; font-family: system-ui, -apple-system, sans-serif; padding: 24px; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #12151e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h1, h2, h3 { color: #d4af37; margin-top: 0; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        th { color: #9ca3af; font-size: 12px; text-transform: uppercase; }
        code { background: #1c202d; color: #d4af37; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .btn { background: linear-gradient(135deg, #d4af37 0%, #aa820a 100%); color: #000; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: #252a38; color: #fff; }
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; white-space: pre-wrap; font-family: monospace; }
        .alert-success { background: rgba(34,197,94,0.15); border: 1px solid #22c55e; color: #86efac; }
        .alert-error { background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #fca5a5; }
        .alert-warning { background: rgba(245,158,11,0.15); border: 1px solid #f59e0b; color: #fde047; }
        input[type="password"] { background: #1c202d; border: 1px solid #2e3547; color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 14px; width: 260px; outline: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Next Gen Relocation — cPanel Deployment Diagnostic</h1>
        <p style="color: #9ca3af;">Verifying server configuration, database connections, and storage permissions.</p>

        <?php if (!empty($actionOutput)): ?>
            <div class="alert alert-<?= htmlspecialchars($actionStatus) ?>">
                <?= htmlspecialchars($actionOutput) ?>
            </div>
        <?php endif; ?>

        <h3>1. PHP & Environment Status</h3>
        <table>
            <tr>
                <td>PHP Version (Requires 8.2+)</td>
                <td><strong><?= PHP_VERSION ?></strong></td>
                <td>
                    <?php if (version_compare(PHP_VERSION, '8.2.0', '>=')): ?>
                        <span class="status-badge badge-success">OK</span>
                    <?php else: ?>
                        <span class="status-badge badge-danger">Upgrade to PHP 8.2+ in cPanel MultiPHP Manager</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Laravel Backend Detected</td>
                <td><code><?= $backendPath ?: 'NOT FOUND' ?></code></td>
                <td>
                    <?php if ($backendPath): ?>
                        <span class="status-badge badge-success">Found</span>
                    <?php else: ?>
                        <span class="status-badge badge-danger">Missing / Upload backend/</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Public Storage Symlink</td>
                <td><code><?= file_exists(__DIR__ . '/storage') ? 'Linked' : 'Not linked' ?></code></td>
                <td>
                    <?php if (file_exists(__DIR__ . '/storage')): ?>
                        <span class="status-badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="status-badge badge-warning">Needs Link</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
            $extensions = ['pdo_mysql', 'openssl', 'mbstring', 'curl', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'];
            foreach ($extensions as $ext):
                $loaded = extension_loaded($ext);
            ?>
            <tr>
                <td>PHP Extension: <code><?= $ext ?></code></td>
                <td><?= $loaded ? 'Enabled' : 'Disabled' ?></td>
                <td>
                    <span class="status-badge <?= $loaded ? 'badge-success' : 'badge-danger' ?>">
                        <?= $loaded ? 'OK' : 'Enable in cPanel' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h3>2. Actions & Automation</h3>
        <?php if (!$authenticated): ?>
            <p style="color: #9ca3af;">Enter your setup key to execute one-click migrations and storage linking:</p>
            <form method="POST">
                <input type="password" name="setup_key" placeholder="Enter setup key (default: nextgen2026)" required />
                <button type="submit" class="btn" style="margin-left: 10px;">Unlock Actions</button>
            </form>
        <?php else: ?>
            <p style="color: #4ade80;">Authenticated. You can execute deployment tasks safely:</p>
            <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px;">
                <form method="POST">
                    <input type="hidden" name="action" value="storage_link" />
                    <button type="submit" class="btn">Create Storage Link</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="migrate" />
                    <button type="submit" class="btn" onclick="return confirm('Run database migrations on cPanel database?')">Run DB Migrations</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="optimize" />
                    <button type="submit" class="btn btn-secondary">Cache Config & Routes</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="cache_clear" />
                    <button type="submit" class="btn btn-secondary">Clear Cache</button>
                </form>
            </div>
            <p style="color: #9ca3af; font-size: 13px; margin-top: 16px;">
                <strong>Security Reminder:</strong> Delete or rename <code>deploy_helper.php</code> once deployment is verified!
            </p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
