FROM python:3.11-slim

ENV PYTHONUNBUFFERED=1 \
    DEBIAN_FRONTEND=noninteractive \
    WHISPER_MODEL=small \
    WHISPER_COMPUTE_TYPE=int8 \
    WHISPER_DEVICE=cpu \
    PORT=9000

# Install ffmpeg and system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy dependencies first for caching
COPY requirements.txt /app/
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY app.py /app/

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
  CMD curl -f http://localhost:9000/health || exit 1

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "9000"]
