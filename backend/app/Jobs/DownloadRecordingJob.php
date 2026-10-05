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
use Illuminate\Support\Str;
use Throwable;

class DownloadRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $callId;
    public string $recordingUrl;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(int $callId, string $recordingUrl)
    {
        $this->callId = $callId;
        $this->recordingUrl = $recordingUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $call = Call::find($this->callId);
        if (!$call) {
            Log::warning("[DownloadRecordingJob] Call ID {$this->callId} not found, skipping.");
            return;
        }

        $disk = config('services.whisper.disk', 'local');

        // Idempotency: If recording_path already exists on disk, proceed directly to transcription
        if (!empty($call->recording_path) && Storage::disk($disk)->exists($call->recording_path)) {
            Log::info("[DownloadRecordingJob] Recording already exists for Call {$call->id}. Dispatching transcription.");
            TranscribeCallJob::dispatch($call->id);
            return;
        }

        $url = $this->recordingUrl;

        // Twilio specific handling: append .mp3 if not already in URL
        if (str_contains($url, 'api.twilio.com') && !str_ends_with($url, '.mp3')) {
            $url .= '.mp3';
        }

        $accountSid = config('services.twilio.account_sid');
        $authToken  = config('services.twilio.auth_token');

        $client = Http::timeout(120);
        if (!empty($accountSid) && !empty($authToken) && str_contains($url, 'api.twilio.com')) {
            $client = $client->withBasicAuth($accountSid, $authToken);
        }

        try {
            $response = $client->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException("Failed to download recording from {$url}: HTTP {$response->status()}");
            }

            $year = now()->format('Y');
            $month = now()->format('m');
            $extension = 'mp3';
            $filename = sprintf('call_%s_%s.%s', $call->id, Str::random(12), $extension);
            $relativePath = "recordings/{$year}/{$month}/{$filename}";

            Storage::disk($disk)->put($relativePath, $response->body());

            $call->update([
                'recording_path'    => $relativePath,
                'recording_url'     => $this->recordingUrl,
                'transcript_status' => 'pending',
                'transcript_error'  => null,
            ]);

            Log::info("[DownloadRecordingJob] Saved recording for Call {$call->id} at {$relativePath}");

            // Dispatch transcription job
            TranscribeCallJob::dispatch($call->id);

        } catch (Throwable $e) {
            Log::error("[DownloadRecordingJob] Error downloading recording for Call {$call->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
