import os
import shutil
import subprocess
import tempfile
from typing import List, Optional
from fastapi import FastAPI, File, UploadFile, Header, HTTPException, Depends, status
from fastapi.responses import JSONResponse
from pydantic import BaseModel

app = FastAPI(
    title="Next Gen CRM Whisper Transcription Service",
    description="Self-hosted speech-to-text microservice using faster-whisper and ffmpeg",
    version="1.0.0",
)

# Configuration from Environment
MODEL_SIZE = os.getenv("WHISPER_MODEL", "small")
COMPUTE_TYPE = os.getenv("WHISPER_COMPUTE_TYPE", "int8")
DEVICE = os.getenv("WHISPER_DEVICE", "cpu")
WHISPER_API_TOKEN = os.getenv("WHISPER_API_TOKEN", "")

# Global model instance
model_instance = None


def get_model():
    global model_instance
    if model_instance is None:
        try:
            from faster_whisper import WhisperModel
            print(f"[WhisperService] Loading model: {MODEL_SIZE} on {DEVICE} ({COMPUTE_TYPE})...")
            model_instance = WhisperModel(MODEL_SIZE, device=DEVICE, compute_type=COMPUTE_TYPE)
            print("[WhisperService] Model loaded successfully.")
        except Exception as e:
            print(f"[WhisperService] Failed to load model: {e}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"Whisper model failed to initialize: {str(e)}",
            )
    return model_instance


def verify_token(authorization: Optional[str] = Header(None)):
    """Validate Bearer token if WHISPER_API_TOKEN is configured."""
    if not WHISPER_API_TOKEN:
        return True

    if not authorization:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Authorization header",
            headers={"WWW-Authenticate": "Bearer"},
        )

    parts = authorization.split()
    if len(parts) != 2 or parts[0].lower() != "bearer" or parts[1] != WHISPER_API_TOKEN:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or unauthorized Bearer token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    return True


class Segment(BaseModel):
    start: float
    end: float
    text: str


class TranscriptionResponse(BaseModel):
    language: str
    text: str
    segments: List[Segment]


def convert_audio_to_wav(input_path: str, output_path: str):
    """
    Convert any incoming audio format (webm, mp3, m4a, amr, ogg, wav)
    to 16kHz mono 16-bit PCM WAV using ffmpeg.
    """
    ffmpeg_bin = shutil.which("ffmpeg") or "ffmpeg"
    cmd = [
        ffmpeg_bin,
        "-y",
        "-i",
        input_path,
        "-vn",
        "-acodec",
        "pcm_s16le",
        "-ar",
        "16000",
        "-ac",
        "1",
        output_path,
    ]
    result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode != 0:
        raise RuntimeError(f"FFmpeg conversion failed: {result.stderr.decode('utf-8', errors='ignore')}")


@app.get("/health")
def health_check():
    """Service health and capability probe."""
    ffmpeg_available = bool(shutil.which("ffmpeg"))
    return {
        "status": "ok",
        "model": MODEL_SIZE,
        "compute_type": COMPUTE_TYPE,
        "device": DEVICE,
        "ffmpeg_available": ffmpeg_available,
        "auth_enabled": bool(WHISPER_API_TOKEN),
    }


@app.post("/transcribe", response_model=TranscriptionResponse)
async def transcribe(
    file: UploadFile = File(...),
    _: bool = Depends(verify_token),
):
    """
    Transcribe uploaded audio file.
    Accepts webm, mp3, m4a, wav, ogg, amr.
    Normalizes audio to 16kHz mono WAV via ffmpeg and runs faster-whisper.
    """
    model = get_model()

    suffix = os.path.splitext(file.filename or "")[1].lower() or ".tmp"
    with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp_in:
        input_path = tmp_in.name
        content = await file.read()
        tmp_in.write(content)

    converted_wav = tempfile.mktemp(suffix=".wav")

    try:
        # Convert to 16kHz mono wav
        try:
            convert_audio_to_wav(input_path, converted_wav)
            audio_source = converted_wav
        except Exception as conv_err:
            print(f"[WhisperService] Warning: ffmpeg conversion notice: {conv_err}. Trying raw input.")
            audio_source = input_path

        # Run transcription
        segments_gen, info = model.transcribe(audio_source, beam_size=5)

        segments_list = []
        full_text_parts = []
        for s in segments_gen:
            clean_text = s.text.strip()
            if clean_text:
                full_text_parts.append(clean_text)
                segments_list.append(
                    Segment(
                        start=round(float(s.start), 2),
                        end=round(float(s.end), 2),
                        text=clean_text,
                    )
                )

        full_text = " ".join(full_text_parts)

        return TranscriptionResponse(
            language=info.language or "en",
            text=full_text,
            segments=segments_list,
        )

    except Exception as e:
        print(f"[WhisperService] Transcription error: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Transcription processing failed: {str(e)}",
        )
    finally:
        # Clean up temporary files
        if os.path.exists(input_path):
            try:
                os.remove(input_path)
            except OSError:
                pass
        if os.path.exists(converted_wav):
            try:
                os.remove(converted_wav)
            except OSError:
                pass


if __name__ == "__main__":
    import uvicorn

    port = int(os.getenv("PORT", 9000))
    uvicorn.run("app:app", host="0.0.0.0", port=port, reload=False)
