@echo off
echo Starting Next Gen CRM Suite with Ngrok...
echo.

echo 1. Starting CRM Panel (Port 5174)...
start "CRM Panel (Vite)" cmd /k "npm --prefix crm-panel run dev"

echo 2. Starting Laravel Backend (Port 8000)...
start "Laravel Backend" cmd /k "cd backend && php artisan serve --port=8000"

echo 3. Starting Gateway Proxy (Port 3000)...
start "Gateway Proxy" cmd /k "node gateway.js"

ping 127.0.0.1 -n 4 >nul

echo 4. Starting Ngrok Tunnel...
start "Ngrok Tunnel" cmd /k "ngrok http 127.0.0.1:3000 --url shield-careless-impulse.ngrok-free.dev"

echo.
echo All services launched!
echo Access the CRM at: https://shield-careless-impulse.ngrok-free.dev/crm/
echo Ngrok Web Inspector: http://127.0.0.1:4040
