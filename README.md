# Next Gen Relocation — Project Monorepo

This workspace contains all repositories and components for the Next Gen Relocation platform, powered by a single shared MySQL database.

## Directory Structure

- **[backend/](file:///e:/crm/backend)**: Laravel 12 codebase containing:
  - **CRM Admin Panel** (Server-rendered Laravel Blade views under `/admin`)
  - **REST API** (Laravel API serving external clients under `/api`)
  - **Models, Migrations & Seeders** (The canonical schema source)
- **[website/](file:///e:/crm/website)**: React (Vite + TypeScript) scaffold for the public-facing website.
- **[database/](file:///e:/crm/database)**: Shared database resources and raw SQL schema references.

---

## Setup & Running the Backend (CRM Admin + API)

### 1. Requirements
- PHP 8.2+
- Composer
- XAMPP / WampServer (MySQL)

### 2. Configuration
The database name is configured in `backend/.env` as `nextgen_crm`. Make sure MySQL is running on port `3306` via XAMPP.

### 3. Setup Commands
Run the following commands inside the `backend/` directory:

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate:fresh --seed
```

### 4. Running the Server
Start the Laravel development server:

```bash
php artisan serve
```

The application will be served at:
- **CRM Admin Panel**: [http://localhost:8000/admin](http://localhost:8000/admin)
- **REST API**: [http://localhost:8000/api](http://localhost:8000/api)
