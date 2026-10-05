# Next Gen CRM — Runtime Architecture

## System Overview

A multi-service CRM platform with real-time video calling, AI transcription, and email automation.

## Core Components (9)

```
┌─────────────────────────────────────────────────────────────────┐
│                         PUBLIC INTERNET                          │
│                     (Unauthenticated Zone)                       │
└──────────────┬──────────────────────────────────────────────────┘
               │ HTTPS (ngrok tunnel)
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      NGROK TUNNEL                               │
│              URL: shield-careless-impulse.ngrok-free.dev         │
│           External-facing, rate-limited, TLS termination         │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      GATEWAY (Port 3000)                        │
│                    Node.js / Express Proxy                       │
│  Routes: /crm → CRM Frontend  |  /api,/admin → Laravel         │
│  Trust boundary: strips internal host headers                   │
└──────┬──────────────┬──────────────┬────────────────────────────┘
       │              │              │
       ▼              ▼              ▼
┌────────────┐ ┌────────────┐ ┌─────────────────────┐
│  CRM       │ │ Laravel    │ │  Whisper Service    │
│  Frontend  │ │ Backend    │ │  (Port 9000)        │
│  Port 5174 │ │ Port 8000  │ │  FastAPI + faster-  │
│  React/Vite│ │ PHP 8.4    │ │  whisper + ffmpeg   │
└──────┬─────┘ └─────┬──────┘ └──────────┬──────────┘
       │              │                   │
       │         ┌────┴─────┐             │
       │         │  MySQL    │             │
       │         │ Port 3306 │◄────────────┘
       │         └────┬─────┘    Queue jobs,
       │              │          calls,
       │         ┌────┴─────┐    transcripts,
       │         │  Reverb   │    sessions
       │         │  WS 8080  │
       │         └───────────┘
       │
       │  ┌───────────────────────────────────────┐
       │  │       QUEUE WORKER (PHP artisan)       │
       │  │  database-backed, processes:           │
       │  │  - TranscribeCallJob                  │
       │  │  - DownloadRecordingJob               │
       │  │  - SendChatNotificationJob            │
       │  │  - SendPushNotificationJob            │
       │  └───────────────────────────────────────┘
       │
```

## Primary Request Path

```
User Browser → Ngrok → Gateway → Laravel Backend → MySQL
                                           │
                                           ▼
                                     Whisper Service
                                      (faster-whisper)
                                           │
                                           ▼
                                      Queue Worker
                                      (background jobs)
```

## External Dependencies

| Dependency | Purpose | Port/Protocol | Trust |
|------------|---------|---------------|-------|
| **Ngrok** | Public tunnel | HTTPS 443 | Trusted (auth'd domain) |
| **ZegoCloud** | Video/WebRTC calls | WSS + REST | Semi-trusted (API keys) |
| **Twilio** | Telephony webhooks | HTTPS | Semi-trusted (webhook sig) |
| **Microsoft Graph** | Outlook/Email sync | HTTPS | Trusted (OAuth2) |
| **Brevo/SMTP** | Transactional email | SMTP 587 | Trusted |
| **Firebase** | Push notifications | FCM HTTPS | Trusted |
| **Reverb** | Real-time broadcasting | WS 8080 | Internal-only |

## Trust Boundaries

```
 ┌─────────────────────── PUBLIC (Untrusted) ──────────────────────┐
 │  Internet → Ngrok → Gateway                                    │
 │  • Raw user input                                              │
 │  • File uploads (recordings)                                   │
 │  • Join tokens (URL params)                                    │
 └─────────────────────── INTERNAL (Trusted) ─────────────────────┘
  │  • Laravel API endpoints (auth'd via Sanctum)                  │
  │  • Whisper service (API token auth)                            │
  │  • MySQL (local-only)                                          │
  │  • Queue worker (DB-backed)                                    │
  └─────────────────────── DATA (Restricted) ─────────────────────┘
     • Recordings on local disk                                   │
     • Transcripts in DB                                          │
     • Contact PII                                                │
```

## Workflow Breakdown / Failure Points

| # | Workflow | Break/Failure Mode | Impact | Mitigation |
|---|----------|-------------------|--------|------------|
| 1 | **Call Recording → Transcription** | Whisper service down | Job stuck `processing`, transcript never stored | Job retries 3x with backoff; status → `failed` with error |
| 2 | **Call Recording → Transcription** | FFmpeg missing | Audio conversion fails, Whisper can't process | Health check reports `ffmpeg_available: false` |
| 3 | **Call Recording → Transcription** | Queue worker not running | Job never dispatched, `pending` forever | Manual `php artisan queue:work` required |
| 4 | **Call Recording → Transcription** | Recording file deleted/moved | `Recording file not found on disk` | Status → `failed`, error logged |
| 5 | **MySQL Down** | All API calls fail | Entire backend unavailable | Restart MySQL via XAMPP |
| 6 | **Ngrok Tunnel Expires** | Public URL breaks | External users can't access CRM | Re-run `ngrok http 3000` |
| 7 | **Gateway Crash** | All routing stops | Frontend + API unreachable | Restart `node gateway.js` |
| 8 | **Whisper Model Cache** | Model not downloaded | Service fails to start | First run downloads ~150MB model |
| 9 | **Symlink Warning** | HF hub cache degraded | Model loading slower on Windows | Run Python as admin or enable Dev Mode |
| 10 | **Brevo SMTP Rate Limit** | Email delivery delays | Welcome/notification emails delayed | Monitor Brevo dashboard |

## Key Configuration

```env
# .env (backend)
WHISPER_SERVICE_URL=http://127.0.0.1:9000
WHISPER_API_TOKEN=whisper-secret-token-crm-2026
CALL_RECORDINGS_DISK=local
QUEUE_CONNECTION=database

# whisper-service/.env
WHISPER_MODEL=small
WHISPER_COMPUTE_TYPE=int8
WHISPER_DEVICE=cpu
WHISPER_API_TOKEN=whisper-secret-token-crm-2026
```


