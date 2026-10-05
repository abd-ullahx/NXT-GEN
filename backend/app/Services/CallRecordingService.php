<?php

namespace App\Services;

use App\Jobs\TranscribeCallJob;
use App\Models\Call;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CallRecordingService
{
    /**
     * Store an audio recording file on the configured private disk,
     * link it to the call record, and dispatch transcription.
     *
     * @param Call $call
     * @param UploadedFile|string $file UploadedFile instance or raw binary content
     * @param string|null $originalExtension
     * @param int|null $durationSeconds
     * @return Call
     */
    public function storeAndDispatch(
        Call $call,
        UploadedFile|string $file,
        ?string $originalExtension = null,
        ?int $durationSeconds = null
    ): Call {
        $disk = config('services.whisper.disk', 'local');
        $year = now()->format('Y');
        $month = now()->format('m');
        $directory = "recordings/{$year}/{$month}";

        if ($file instanceof UploadedFile) {
            $extension = $file->getClientOriginalExtension() ?: ($originalExtension ?: 'webm');
            $filename = sprintf(
                'call_%s_%s.%s',
                $call->id,
                Str::random(12),
                strtolower($extension)
            );
            $path = $file->storeAs($directory, $filename, $disk);
        } else {
            // Binary string / raw stream
            $extension = $originalExtension ?: 'webm';
            $filename = sprintf(
                'call_%s_%s.%s',
                $call->id,
                Str::random(12),
                strtolower($extension)
            );
            $path = "{$directory}/{$filename}";
            Storage::disk($disk)->put($path, $file);
        }

        $updates = [
            'recording_path'    => $path,
            'transcript_status' => 'pending',
            'transcript_error'  => null,
        ];

        if ($durationSeconds !== null && $durationSeconds > 0) {
            $updates['duration_seconds'] = $durationSeconds;
        }

        if (!$call->ended_at) {
            $updates['ended_at'] = now();
        }

        $call->update($updates);

        // Dispatch queued transcription job
        TranscribeCallJob::dispatch($call->id);

        return $call->fresh();
    }
}
