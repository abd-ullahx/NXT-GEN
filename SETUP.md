# Next Gen CRM — Call to Text (Whisper AI) & Recording Setup Guide

This guide details how to run, configure, and test the **Call-to-Text** automated transcription feature in Next Gen CRM using your self-hosted **Whisper** microservice and free **WebRTC / Manual upload** call recording.

---

## 1. System Architecture

```
                                  Agent Browser (CRM)
                                   (Web Audio Context)
                                            │
                                ┌───────────┴───────────┐
                                ▼                       ▼
                           Local Mic                Remote Audio
                                └───────────┬───────────┘
                                            ▼
                               Audio Destination Mixer
                                            │
                                            ▼
                                MediaRecorder (webm/opus)
                                            │
                         Call Ends ─────────┘
                                │
                                ▼
               POST /api/calls/{call}/recording
                                │
                                ▼
                       CallRecordingService
                     (Private Storage on Disk)
                                │
                                ▼
                        TranscribeCallJob
                         (Queued Worker)
                                │
                                ▼
                   Whisper Microservice (:9000)
                   ├── FFmpeg (16kHz Mono WAV)
                   └── faster-whisper Model
                                │
                                ▼
                Save Transcript & Language to DB
                                │
                                ▼
                 Live View in CRM & Admin Panel
```

---

## 2. Running the Whisper Microservice

The microservice runs in `/whisper-service` on port `9000`.

### Option A: Using Docker / Docker Compose

If you have Docker Desktop or Docker engine installed:

```bash
cd whisper-service
docker-compose up -d --build
```

Check health:
```bash
curl http://localhost:9000/health
```

---

### Option B: Native Python (No Docker Required)

#### 1. Prerequisites
- **Python 3.10+** installed
- **FFmpeg** installed and accessible in system `PATH` (`ffmpeg -version`)

#### 2. Virtual Environment Setup
```bash
cd whisper-service
python -m venv venv

# Windows (PowerShell / CMD):
.\venv\Scripts\activate

# Linux / macOS:
source venv/bin/activate

# Install dependencies:
pip install -r requirements.txt
```

#### 3. Start the Microservice
```bash
# Set configuration env vars:
set WHISPER_MODEL=small
set WHISPER_COMPUTE_TYPE=int8
set WHISPER_DEVICE=cpu
set WHISPER_API_TOKEN=whisper-secret-token-crm-2026

uvicorn app:app --host 0.0.0.0 --port 9000
```

Verify service status:
```bash
curl http://127.0.0.1:9000/health
```

---

## 3. Running the Laravel Queue Worker

Transcriptions are processed asynchronously via queued jobs with automated retries and backoff (`60s, 300s, 900s`).

### Development
Inside the `backend/` directory:

```bash
cd backend
php artisan queue:work --tries=3 --timeout=900
```

### Production (Supervisor Configuration)
On a Linux server, create `/etc/supervisor/conf.d/nextgen-worker.conf`:

```ini
[program:nextgen-crm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/crm/backend/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=900
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/crm/backend/storage/logs/worker.log
stopwaitsecs=900
```

Apply Supervisor config:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start nextgen-crm-worker:*
```

---

## 4. Telephony Provider Setup (Twilio Webhook Fallback)

If using external telephony (e.g. Twilio Voice / Elastic SIP):

1. **In Twilio Console**:
   - Go to **Voice** $\to$ **Manage** $\to$ **Numbers** $\to$ Click active number.
   - Under **Call Recording / Recording Status Callback**:
     - Set URL: `https://shield-careless-impulse.ngrok-free.dev/api/webhooks/twilio/recording`
     - HTTP Method: `POST`
     - Events: `Completed`
2. **In `backend/.env`**:
   ```env
   TWILIO_ACCOUNT_SID=ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
   TWILIO_AUTH_TOKEN=your_auth_token_here
   ```
   The `TwilioWebhookController` automatically validates `X-Twilio-Signature`, downloads the audio to the private disk with HTTP basic authentication via `DownloadRecordingJob`, and dispatches `TranscribeCallJob`.

---

## 5. Free Browser Calling & Local Testing with Ngrok

No paid telephony provider is needed. The CRM provides a **free WebRTC browser calling** workflow:

1. **Start the Stack**:
   ```cmd
   start-ngrok.bat
   ```
2. **Open the CRM**:
   Navigate to [https://shield-careless-impulse.ngrok-free.dev/admin/calls](https://shield-careless-impulse.ngrok-free.dev/admin/calls) or `http://localhost:8000/admin/calls`.
3. **Make a Call**:
   - Click **Start Browser Call**.
   - Select a contact.
   - Copy the generated **Contact Join Link** (`/call/join/{token}`) and open it in an incognito window or on a phone.
4. **Consent & Mixing**:
   - Contact is shown the mandatory recording consent notice: *"⚠️ Call Recording & Transcription Notice"*.
   - Contact clicks **Accept & Join Call**.
   - The browser mixes local microphone and remote incoming audio via `AudioContext` and records via `MediaRecorder`.
5. **End & Auto-Transcription**:
   - Click **End & Transcribe Call**.
   - The audio (`audio/webm`) is uploaded to `POST /api/calls/{call}/recording`.
   - The queue worker picks up `TranscribeCallJob`, sends audio to the Whisper microservice, saves the transcript to MySQL, and displays the transcript directly on the call detail page!

---

## 6. Manual Audio Upload

For calls recorded on mobile phones or external voice memos:
1. Go to **Calls & Transcripts** in the admin panel.
2. Click **Upload Recording**.
3. Choose any audio file (`.mp3`, `.m4a`, `.wav`, `.webm`, `.ogg`, `.amr`).
4. Click **Upload & Transcribe**.
5. Whisper normalizes the audio to 16kHz mono WAV via FFmpeg and produces the transcript.
