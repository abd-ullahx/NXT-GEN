# Next Gen Relocation — cPanel Production Deployment Guide

This guide walks you step-by-step through deploying this project onto your cPanel server where the main domain points to `public_html`.

---

## 🎯 Architecture on Your Domain

When deployed to cPanel, your domain behaves as follows:

| URL Path | Service | Where it is Served From |
| :--- | :--- | :--- |
| **`https://yourdomain.com/`** | **Main Website** | `public_html/index.html` + `public_html/assets/` |
| **`https://yourdomain.com/crm/`** | **CRM Panel** | `public_html/crm/index.html` + `public_html/crm/assets/` |
| **`https://yourdomain.com/api/*`** | **Laravel Backend API** | `public_html/index.php` → `backend/` |
| **`https://yourdomain.com/storage/*`** | **Uploaded Files & Media** | `public_html/storage` (symlink to backend storage) |
| **`https://yourdomain.com/book-survey`** | **Customer Survey Booking** | `public_html/index.php` |
| **`https://yourdomain.com/quotation/*`** | **Quotation Approvals** | `public_html/index.php` |
| **`https://yourdomain.com/payment/*`** | **Checkout & Payments** | `public_html/index.php` |
| **`https://yourdomain.com/job/*`** | **Job Reschedule & Confirm** | `public_html/index.php` |
| **`https://yourdomain.com/call/*`** | **Video/Audio Calling** | `public_html/index.php` |

---

## 📁 Directory Structure on cPanel

On your cPanel account, your home directory layout will be:

```
/home/your_cpanel_user/
│
├── backend/                       <-- UPLOAD THE BACKEND FOLDER HERE (Outside public_html for security)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── storage/
│   ├── vendor/
│   ├── .env                       <-- Production .env configuration
│   └── artisan
│
└── public_html/                   <-- UPLOAD CONTENTS OF cpanel_deploy/public_html/ HERE
    ├── .htaccess                  <-- Master routing rules
    ├── index.php                  <-- Laravel bridge router
    ├── index.html                 <-- Luxury Relocation Website
    ├── assets/                    <-- Website CSS & JS
    ├── deploy_helper.php          <-- 1-Click diagnostic & setup tool
    ├── storage -> ../backend/storage/app/public  <-- Symlink
    └── crm/                       <-- CRM Application Subfolder
        ├── .htaccess              <-- TanStack SPA router fallback
        ├── index.html             <-- CRM Entrypoint
        ├── assets/                <-- CRM CSS & JS chunks
        ├── robots.txt
        └── zego-call.html
```

> **Why keep `backend/` outside `public_html`?**  
> Keeping the Laravel backend in `/home/user/backend/` ensures your `.env` file, database passwords, API credentials, and application code can never be accessed or downloaded by a web browser.

---

## 🚀 Step-by-Step Deployment Instructions

### Step 1: Build the Production Bundles (Done locally)

Run the automated preparation script from your local workspace:

```bash
npm run build:cpanel
```

This compiles the website, bundles the CRM panel, and organizes everything into the local folder:  
`cpanel_deploy/public_html/`

---

### Step 2: Upload Files via cPanel File Manager (or FTP)

1. Log into your **cPanel**.
2. Open **File Manager**.
3. **Upload the `backend/` folder:**
   - In your home directory (`/home/username/`), upload the entire `backend` directory (including `vendor/`).
   - If uploading as a ZIP file, upload `backend.zip` to `/home/username/` and click **Extract**.
4. **Upload the `public_html/` contents:**
   - Go inside `/home/username/public_html/`.
   - Upload all files and folders located inside your local `cpanel_deploy/public_html/`:
     - `.htaccess` *(Make sure "Show Hidden Files" is turned on in cPanel File Manager settings)*
     - `index.php`
     - `index.html`
     - `assets/`
     - `crm/` (with its `.htaccess`, `index.html`, and `assets/`)
     - `deploy_helper.php`

---

### Step 3: Create MySQL Database in cPanel

1. In cPanel, search for **MySQL® Database Wizard**.
2. **Step 1:** Create database name (e.g. `youruser_crm`).
3. **Step 2:** Create database user & password (e.g. `youruser_crmuser` and a strong password).
4. **Step 3:** Assign **ALL PRIVILEGES** to the user for this database.
5. Save your database name, username, and password.

---

### Step 4: Configure `backend/.env`

In cPanel File Manager, open `/home/username/backend/.env` (or copy `.env.production.example` to `.env`):

```dotenv
APP_NAME="Next Gen Relocation"
APP_ENV=production
APP_KEY=base64:your_app_key_here
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Connection
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=youruser_crm
DB_USERNAME=youruser_crmuser
DB_PASSWORD=your_db_password_here

# Frontend & Public URLs
FRONTEND_URL=https://yourdomain.com/crm
PUBLIC_BASE_URL=https://yourdomain.com

# Email Settings (Brevo or cPanel SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_account_email
MAIL_PASSWORD=your_brevo_smtp_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="info@yourdomain.com"
MAIL_FROM_NAME="Next Gen Relocation"

# Azure Outlook OAuth Callback (if used)
MICROSOFT_REDIRECT_URI=https://yourdomain.com/api/outlook/callback

# ZegoCloud Video Calling
ZEGO_APP_ID=your_zego_app_id
ZEGO_SERVER_SECRET=your_zego_server_secret
ZEGO_APP_SIGN=your_zego_app_sign
```

---

### Step 5: Run Migrations & Storage Link (Using `deploy_helper.php`)

You don't need SSH access to run migrations or create the storage link!

1. Open your browser and navigate to:  
   `https://yourdomain.com/deploy_helper.php`
2. Enter the default security key: `nextgen2026`
3. Click:
   - **Create Storage Link** *(Creates `public_html/storage` pointing to `backend/storage/app/public`)*
   - **Run DB Migrations** *(Creates all database tables in MySQL)*
   - **Cache Config & Routes** *(Optimizes Laravel for production performance)*
4. Once completed, **delete or rename** `deploy_helper.php` from `public_html/` for security.

*(Alternatively, if you have cPanel **Terminal** access, you can simply run:)*
```bash
cd /home/username/backend
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

---

### Step 6: Verify PHP Version & Modules

In cPanel:
1. Search for **MultiPHP Manager** and ensure your domain is set to **PHP 8.2** or **PHP 8.3**.
2. Search for **MultiPHP INI Editor**:
   - `upload_max_filesize = 64M`
   - `post_max_size = 64M`
   - `memory_limit = 256M`
   - `max_execution_time = 300`

---

## ✅ Testing Your Setup

After uploading:

1. Visit **`https://yourdomain.com`**:
   - The luxury relocation website loads instantly.
   - Quote submission sends leads to `/api/leads`.
   - The "CRM Panel" buttons navigate to `/crm`.
2. Visit **`https://yourdomain.com/crm/`**:
   - The Next Gen CRM sign-in page displays.
   - Logging in directs to `/crm/app/dashboard`.
   - Refreshing any inner page (e.g. `/crm/app/leads`) retains state without 404 errors.
3. Test **`https://yourdomain.com/api/status`**:
   - Returns JSON: `{"name":"Next Gen Relocation API","status":"online","database":"MySQL Connected"}`.
