# Next Gen CRM — Ngrok Deployment Guide

This guide explains how to run the entire Next Gen CRM stack locally and expose it securely through **ngrok** using your domain:
👉 **`https://shield-careless-impulse.ngrok-free.dev/crm/`**

---

## 1. System Architecture

Rather than pointing ngrok directly to the frontend or backend, the system uses a **unified gateway** (`gateway.js`) running on port `3000`:

```
               Internet
                  │
                  ▼
   https://shield-careless-impulse.ngrok-free.dev
                  │ (ngrok tunnel)
                  ▼
       Next Gen Gateway (Port 3000)
       ┌──────────┴──────────┐
       ▼                     ▼
  /crm, /crm/*          /api/*, /admin/*
CRM Vite Frontend     Laravel Backend API
  (Port 5174)           (Port 8000)
```

- **Ngrok Tunnel**: Forwards all public requests to port `3000`.
- **Gateway (`gateway.js`)**:
  - Directs `/crm/*` to the React/Vite CRM panel (`http://127.0.0.1:5174`).
  - Directs `/api/*`, `/admin/*`, `/sanctum/*` to the Laravel backend (`http://127.0.0.1:8000`).
  - Automatically redirects `/` and `/crm` to `/crm/`.

---

## 2. Prerequisites

1. **MySQL Database**: Ensure MySQL is started (e.g., via XAMPP Control Panel).
2. **Ngrok CLI**: Make sure ngrok is installed and authenticated:
   ```bash
   ngrok config add-authtoken <YOUR_AUTHTOKEN>
   ```
3. **Environment Config**:
   Verify `backend/.env` has:
   ```env
   APP_URL=https://shield-careless-impulse.ngrok-free.dev
   FRONTEND_URL=https://shield-careless-impulse.ngrok-free.dev
   SANCTUM_STATEFUL_DOMAINS="shield-careless-impulse.ngrok-free.dev,..."
   ```

---

## 3. Step-by-Step Manual Startup

Run the following commands across **4 separate terminal windows**:

### Terminal 1: CRM Frontend (Port 5174)
```bash
npm --prefix crm-panel run dev
```

### Terminal 2: Laravel Backend (Port 8000)
```bash
cd backend
php artisan serve --port=8000
```

### Terminal 3: Gateway Proxy (Port 3000)
```bash
node gateway.js
```

### Terminal 4: Ngrok Tunnel
```bash
ngrok http 127.0.0.1:3000 --url shield-careless-impulse.ngrok-free.dev
```

---

## 4. One-Click Startup Script

You can also start all 4 services at once using `start-ngrok.bat`:

```cmd
start-ngrok.bat
```

Or via npm:
```bash
npm run start:tunnel
```

---

## 5. Verifying the Deployment

1. Open your browser and navigate to:
   - **CRM Application**: [https://shield-careless-impulse.ngrok-free.dev/crm/](https://shield-careless-impulse.ngrok-free.dev/crm/)
   - **Admin Panel**: [https://shield-careless-impulse.ngrok-free.dev/admin](https://shield-careless-impulse.ngrok-free.dev/admin)
   - **API Health**: [https://shield-careless-impulse.ngrok-free.dev/api/health](https://shield-careless-impulse.ngrok-free.dev/api/health)
2. To inspect live requests flowing through the tunnel, open the ngrok web inspector at:
   - **Ngrok Inspector**: [http://127.0.0.1:4040](http://127.0.0.1:4040)
