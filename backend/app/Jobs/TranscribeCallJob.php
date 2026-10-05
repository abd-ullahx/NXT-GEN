<?php

namespace App\Jobs;

use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $callId;
    public bool $force;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Backoff schedule: 1min, 5min, 15min
     */
    public array $backoff = [60, 300, 900];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900;

    /**
     * Create a new job instance.
     */
    public function __construct(int $callId, bool $force = false)
    {
        $this->callId = $callId;
        $this->force = $force;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $call = Call::find($this->callId);
        if (!$call) {
            Log::warning("[TranscribeCallJob] Call ID {$this->callId} not found, skipping.");
            return;
        }

        // Idempotency: skip if already transcribed and not forcing retry
        if (!$this->force && $call->transcript_status === 'done' && !empty($call->transcript)) {
            Log::info("[TranscribeCallJob] Call {$call->id} is already transcribed. Skipping.");
            return;
        }

        if (empty($call->recording_path)) {
            $errorMsg = "Call {$call->id} has no recording_path.";
            Log::warning("[TranscribeCallJob] {$errorMsg}");
            $call->update([
                'transcript_status' => 'failed',
                'transcript_error'  => $errorMsg,
            ]);
            return;
        }

        $disk = config('services.whisper.disk', 'local');
        if (!Storage::disk($disk)->exists($call->recording_path)) {
            $errorMsg = "Recording file not found on disk [{$disk}]: {$call->recording_path}";
            Log::error("[TranscribeCallJob] {$errorMsg}");
            $call->update([
                'transcript_status' => 'failed',
                'transcript_error'  => $errorMsg,
            ]);
            return;
        }

        // Mark status as processing
        $call->update([
            'transcript_status' => 'processing',
            'transcript_error'  => null,
        ]);

        $whisperUrl = rtrim(config('services.whisper.url', 'http://127.0.0.1:9000'), '/');
        $token = config('services.whisper.token');
        $requestTimeout = (int) config('services.whisper.timeout', 900);

        $fileContents = Storage::disk($disk)->get($call->recording_path);
        $filename = basename($call->recording_path);

        $client = Http::timeout($requestTimeout);
        if (!empty($token)) {
            $client = $client->withToken($token);
        }

        try {
            $response = $client->attach('file', $fileContents, $filename)
                ->post("{$whisperUrl}/transcribe");

            if ($response->successful()) {
                $data = $response->json();
                $transcriptText = trim($data['text'] ?? '');
                $detectedLanguage = $data['language'] ?? 'en';

                $call->update([
                    'transcript'          => $transcriptText,
                    'transcript_language' => $detectedLanguage,
                    'transcript_status'   => 'done',
                    'transcript_error'    => null,
                ]);

                Log::info("[TranscribeCallJob] Successfully transcribed Call {$call->id} (Language: {$detectedLanguage})");
            } else {
                $statusCode = $response->status();
                $errorDetail = $response->json('detail') ?: $response->body();
                throw new \RuntimeException("Whisper service returned HTTP {$statusCode}: {$errorDetail}");
            }
        } catch (Throwable $e) {
            Log::error("[TranscribeCallJob] Error transcribing call {$call->id}: " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                $call->update([
                    'transcript_status' => 'failed',
                    'transcript_error'  => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $call = Call::find($this->callId);
        if ($call) {
            $call->update([
                'transcript_status' => 'failed',
                'transcript_error'  => $exception ? $exception->getMessage() : 'Exhausted retry attempts',
            ]);
        }

        Log::error("[TranscribeCallJob] Permanent failure for Call {$this->callId}: " . ($exception?->getMessage() ?? 'Unknown error'));
    }
}
