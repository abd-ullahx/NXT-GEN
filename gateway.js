import express from 'express';
import http from 'http';
import compression from 'compression';

const app = express();
app.use(compression());
const PORT = process.env.PORT || 3000;

const WEBSITE_TARGET = process.env.WEBSITE_TARGET || 'http://127.0.0.1:5173';
const CRM_TARGET = process.env.CRM_TARGET || 'http://127.0.0.1:5174';
const BACKEND_TARGET = process.env.BACKEND_TARGET || 'http://127.0.0.1:8000';

app.use((req, res) => {
  let url = req.originalUrl || req.url || '/';

  // Normalize /crm (ensure /crm/ for static Vite router)
  if (url === '/crm') {
    return res.redirect('/crm/');
  }

  // Normalize /admin to /admin/dashboard
  if (url === '/admin' || url === '/admin/') {
    return res.redirect('/admin/dashboard');
  }

  // Normalize /app to /crm/app
  if (url === '/app' || url === '/app/' || url.startsWith('/app/')) {
    return res.redirect('/crm' + url);
  }

  let targetBase = WEBSITE_TARGET;
  let targetPath = url;

  if (url.startsWith('/crm')) {
    targetBase = CRM_TARGET;
  } else if (
    url.startsWith('/admin') ||
    url.startsWith('/api') ||
    url.startsWith('/storage') ||
    url.startsWith('/sanctum') ||
    url.startsWith('/book-survey') ||
    url.startsWith('/quotation') ||
    url.startsWith('/payment') ||
    url.startsWith('/job') ||
    url.startsWith('/call')
  ) {
    targetBase = BACKEND_TARGET;
  }

  const targetUrl = new URL(targetPath, targetBase);

  const options = {
    hostname: targetUrl.hostname,
    port: targetUrl.port,
    path: targetUrl.pathname + targetUrl.search,
    method: req.method,
    headers: {
      ...req.headers,
      host: targetUrl.host,
      'x-forwarded-host': req.headers.host,
      'x-forwarded-proto': req.headers['x-forwarded-proto'] || 'http',
    },
  };

  const proxyReq = http.request(options, (proxyRes) => {
    const headers = { ...proxyRes.headers };
    if (headers.location) {
      headers.location = headers.location.replace(/https?:\/\/(127\.0\.0\.1|localhost):8000/g, '');
      headers.location = headers.location.replace(/https?:\/\/(127\.0\.0\.1|localhost):5174/g, '');
      headers.location = headers.location.replace(/https?:\/\/(127\.0\.0\.1|localhost):5173/g, '');
    }
    res.writeHead(proxyRes.statusCode, headers);
    proxyRes.pipe(res, { end: true });
  });

  proxyReq.on('error', (err) => {
    console.error(`[PROXY ERROR] ${req.method} ${url}:`, err.message);
    if (!res.headersSent) {
      res.status(502).send(`Bad Gateway: Failed to reach service (${err.message})`);
    }
  });
  req.pipe(proxyReq, { end: true });
});

const server = http.createServer(app);

server.listen(PORT, '0.0.0.0', () => {
  console.log(`=======================================================`);
  console.log(`✨ Next Gen Gateway active on http://localhost:${PORT}`);
  console.log(`🔗 Root website (/)     -> ${WEBSITE_TARGET}`);
  console.log(`🔗 CRM panel (/crm)     -> ${CRM_TARGET}/crm/`);
  console.log(`🔗 Admin panel (/admin) -> ${BACKEND_TARGET}/admin/dashboard`);
  console.log(`🔗 API backend (/api)   -> ${BACKEND_TARGET}/api`);
  console.log(`=======================================================`);
});

// Also bind ::1 (IPv6) so ngrok connections to localhost:3000 never get refused on Windows
try {
  const ipv6Server = http.createServer(app);
  ipv6Server.listen(PORT, '::1', () => {});
  ipv6Server.on('error', () => {});
} catch (e) {
  // Ignore
}

