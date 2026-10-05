import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawn } from 'child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const deployDir = path.join(rootDir, 'cpanel_deploy');
const publicHtmlDir = path.join(deployDir, 'public_html');
const crmDir = path.join(publicHtmlDir, 'crm');

console.log('🚀 Preparing cPanel deployment package...');

// Clean and ensure directories exist
if (fs.existsSync(crmDir)) {
  fs.rmSync(crmDir, { recursive: true, force: true });
}
fs.mkdirSync(crmDir, { recursive: true });

// 1. Copy website dist files to public_html
const websiteDist = path.join(rootDir, 'website', 'dist');
if (fs.existsSync(websiteDist)) {
  console.log('📦 Copying website build to public_html...');
  copyFolderSync(websiteDist, publicHtmlDir);
} else {
  console.warn('⚠️ Website dist not found. Run npm run build in /website first.');
}

// 2. Copy crm-panel build files to public_html/crm
const crmPublic = path.join(rootDir, 'crm-panel', '.output', 'public');
if (fs.existsSync(crmPublic)) {
  console.log('📦 Copying CRM build to public_html/crm...');
  copyFolderSync(crmPublic, crmDir);
} else {
  console.warn('⚠️ CRM build not found in crm-panel/.output/public.');
}

// 2b. Generate /crm/index.html via SSR pre-rendering
const serverEntry = path.join(rootDir, 'crm-panel', '.output', 'server', 'index.mjs');
if (fs.existsSync(serverEntry)) {
  console.log('⚡ Pre-rendering /crm/index.html using SSR server...');
  const port = 3889;
  const child = spawn(process.execPath, [serverEntry], {
    env: { ...process.env, PORT: String(port) },
    stdio: 'ignore',
  });

  let rendered = false;
  for (let i = 0; i < 25; i++) {
    await new Promise((r) => setTimeout(r, 400));
    try {
      const res = await fetch(`http://127.0.0.1:${port}/crm`);
      if (res.ok) {
        const html = await res.text();
        fs.writeFileSync(path.join(crmDir, 'index.html'), html, 'utf8');
        console.log('✅ Generated public_html/crm/index.html (' + html.length + ' bytes)');
        rendered = true;
        break;
      }
    } catch {}
  }
  child.kill();
  if (!rendered) {
    console.warn('⚠️ Could not pre-render /crm/index.html from SSR server.');
  }
}

// 3. Create .htaccess in public_html
const masterHtaccess = `# ==============================================================================
# Next Gen Relocation — cPanel Production Master .htaccess
# Handles:
# 1. Main Website at / (and subpages) -> public_html/index.html
# 2. CRM Panel at /crm/ -> public_html/crm/index.html
# 3. Laravel Backend -> public_html/index.php
#    (/api, /sanctum, /admin, /book-survey, /quotation, /payment, /job, /call, etc.)
# 4. Uploaded public assets -> public_html/storage/
# ==============================================================================

<IfModule mod_negotiation.c>
    Options -MultiViews -Indexes
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Pass Authorization Header for PHP-FPM / FastCGI / Sanctum
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Force HTTPS (optional: uncomment if SSL is active on your domain)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Normalize /crm to /crm/ (Redirect trailing slash for directory)
    RewriteRule ^crm$ /crm/ [R=301,L]

    # Normalize /admin to /admin/dashboard
    RewriteRule ^admin/?$ /index.php [L]

    # Normalize /app/* shortcuts to /crm/app/*
    RewriteRule ^app/(.*)$ /crm/app/$1 [R=301,L]

    # --------------------------------------------------------------------------
    # CRM Panel Static Assets & SPA Router (/crm/...)
    # --------------------------------------------------------------------------
    # If file exists inside /crm (e.g. /crm/assets/style.css, /crm/zego-call.html), serve it
    RewriteCond %{REQUEST_URI} ^/crm/
    RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -f [OR]
    RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -d
    RewriteRule ^ - [L]

    # SPA routing for CRM panel (fallback to /crm/index.html)
    RewriteCond %{REQUEST_URI} ^/crm/
    RewriteRule ^crm/(.*)$ /crm/index.html [L]

    # --------------------------------------------------------------------------
    # Laravel Backend Dynamic Routes (/api, /sanctum, /admin, etc.)
    # --------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} ^/(api|sanctum|admin|book-survey|quotation|payment|job|call|survey|chat|surveyor|invoice|customer|webhooks)(/.*)?$ [NC]
    RewriteRule ^ index.php [L]

    # --------------------------------------------------------------------------
    # Laravel Public Storage Link (/storage/...)
    # --------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} ^/storage/
    RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -f
    RewriteRule ^ - [L]

    RewriteCond %{REQUEST_URI} ^/storage/
    RewriteRule ^ index.php [L]

    # --------------------------------------------------------------------------
    # Main Website Static Files & SPA Fallback (/)
    # --------------------------------------------------------------------------
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Send all other requests to Website index.html
    RewriteRule ^ index.html [L]
</IfModule>

# Performance: Gzip / Deflate Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
`;

fs.writeFileSync(path.join(publicHtmlDir, '.htaccess'), masterHtaccess, 'utf8');
console.log('✅ Created public_html/.htaccess');

// 4. Create .htaccess in public_html/crm/
const crmHtaccess = `<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /crm/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.html [L]
</IfModule>
`;
fs.writeFileSync(path.join(crmDir, '.htaccess'), crmHtaccess, 'utf8');
console.log('✅ Created public_html/crm/.htaccess');

// 5. Create index.php in public_html for Laravel
const indexPhp = `<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;

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
`;

fs.writeFileSync(path.join(publicHtmlDir, 'index.php'), indexPhp, 'utf8');
console.log('✅ Created public_html/index.php');

// 6. Copy deploy_helper.php
const helperSrc = path.join(__dirname, 'deploy_helper.php');
if (fs.existsSync(helperSrc)) {
  fs.copyFileSync(helperSrc, path.join(publicHtmlDir, 'deploy_helper.php'));
  console.log('✅ Copied public_html/deploy_helper.php');
}

// Helper to copy folders recursively
function copyFolderSync(from, to) {
  fs.mkdirSync(to, { recursive: true });
  for (const element of fs.readdirSync(from)) {
    const fromPath = path.join(from, element);
    const toPath = path.join(to, element);
    const stat = fs.lstatSync(fromPath);
    if (stat.isDirectory()) {
      copyFolderSync(fromPath, toPath);
    } else {
      fs.copyFileSync(fromPath, toPath);
    }
  }
}

console.log('🎉 public_html folder is ready inside: ' + publicHtmlDir);
