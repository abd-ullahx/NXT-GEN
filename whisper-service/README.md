# Self-Hosted Whisper Transcription Service

A high-performance, containerized speech-to-text microservice built with **FastAPI**, **faster-whisper**, and **FFmpeg** for Next Gen CRM call transcription.

---

## Features

- **Fast & Accurate**: Powered by `faster-whisper` (CTranslate2 acceleration).
- **Format Normalization**: Automatically normalizes any audio input (webm, mp3, m4a, amr, wav, ogg) to 16kHz mono WAV via FFmpeg before inference.
- **Bearer Token Auth**: Optional API token validation (`WHISPER_API_TOKEN`).
- **Rich Output**: Returns detected language, unified transcript text, and timestamped speech segments (`[{start, end, text}]`).
- **Health Endpoint**: Probe at `/health` for uptime and FFmpeg status.

---

## Quickstart

### Method A: Docker Compose (Recommended)

```bash
cd whisper-service
docker-compose up -d --build
```

The service will be listening on `http://localhost:9000`.

To monitor logs:
```bash
docker-compose logs -f
```

---

### Method B: Native Python (Local Development)

#### 1. Prerequisites
- Python 3.10+
- FFmpeg installed in system PATH (`ffmpeg -version`)

#### 2. Install Dependencies
```bash
cd whisper-service
python -m venv venv
# On Windows:
.\venv\Scripts\activate
# On Linux/macOS:
source venv/bin/activate

pip install -r requirements.txt
```

#### 3. Run Service
```bash
# Optional environment variables
export WHISPER_MODEL="small"
export WHISPER_COMPUTE_TYPE="int8"
export WHISPER_DEVICE="cpu" # or "cuda" if GPU is available
export WHISPER_API_TOKEN="whisper-secret-token-crm-2026"

uvicorn app:app --host 0.0.0.0 --port 9000
```

---

## API Endpoints

### 1. Health Probe
```http
GET /health
```

**Response**:
```json
{
  "status": "ok",
  "model": "small",
  "compute_type": "int8",
  "device": "cpu",
  "ffmpeg_available": true,
  "auth_enabled": true
}
```

---

### 2. Transcribe Audio
```http
POST /transcribe
Authorization: Bearer whisper-secret-token-crm-2026
Content-Type: multipart/form-data
```

**Form Data**:
- `file`: Audio file (mp3, webm, wav, m4a, amr, ogg)

**Response**:
```json
{
  "language": "en",
  "text": "Hello, thank you for calling Next Gen Relocation. How can I help you today?",
  "segments": [
    {
      "start": 0.0,
      "end": 2.8,
      "text": "Hello, thank you for calling Next Gen Relocation."
    },
    {
      "start": 2.9,
      "end": 4.5,
      "text": "How can I help you today?"
    }
  ]
}
```
