<?php

namespace Tests\Feature;

use App\Jobs\TranscribeCallJob;
use App\Models\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscribeCallJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Config::set('services.whisper.disk', 'local');
        Config::set('services.whisper.url', 'http://127.0.0.1:9000');
        Config::set('services.whisper.token', 'test-token');
    }

    public function test_whisper_transcription_success(): void
    {
        // 1. Prepare dummy recording on storage
        $relativePath = 'recordings/2026/09/sample_call.webm';
        Storage::disk('local')->put($relativePath, 'DUMMY_AUDIO_STREAM_BYTES');

        $call = Call::create([
            'provider_call_sid' => 'test_call_sid_1',
            'recording_path'    => $relativePath,
            'transcript_status' => 'pending',
        ]);

        // 2. Fake Whisper HTTP response
        Http::fake([
            'http://127.0.0.1:9000/transcribe' => Http::response([
                'language' => 'en',
                'text'     => 'Customer confirmed 3-bed relocation for next Friday.',
                'segments' => [
                    [
                        'start' => 0.0,
                        'end'   => 3.5,
                        'text'  => 'Customer confirmed 3-bed relocation for next Friday.',
                    ],
                ],
            ], 200),
        ]);

        // 3. Execute Job
        (new TranscribeCallJob($call->id))->handle();

        // 4. Assert Database updates
        $call->refresh();
        $this->assertEquals('done', $call->transcript_status);
        $this->assertEquals('Customer confirmed 3-bed relocation for next Friday.', $call->transcript);
        $this->assertEquals('en', $call->transcript_language);
        $this->assertNull($call->transcript_error);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transcribe') &&
                   $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_whisper_transcription_failure_marks_call_failed(): void
    {
        $relativePath = 'recordings/2026/09/sample_call_err.webm';
        Storage::disk('local')->put($relativePath, 'DUMMY_BYTES');

        $call = Call::create([
            'provider_call_sid' => 'test_call_sid_err',
            'recording_path'    => $relativePath,
            'transcript_status' => 'pending',
        ]);

        Http::fake([
            'http://127.0.0.1:9000/transcribe' => Http::response([
                'detail' => 'FFmpeg decoding failed: corrupt header',
            ], 500),
        ]);

        $job = new TranscribeCallJob($call->id);

        try {
            $job->handle();
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            // Trigger failure handler
            $job->failed($e);
        }

        $call->refresh();
        $this->assertEquals('failed', $call->transcript_status);
        $this->assertStringContainsString('Whisper service returned HTTP 500', $call->transcript_error);
    }

    public function test_whisper_job_is_idempotent(): void
    {
        $relativePath = 'recordings/2026/09/sample_call_done.webm';
        Storage::disk('local')->put($relativePath, 'DUMMY_BYTES');

        $call = Call::create([
            'provider_call_sid' => 'test_call_sid_done',
            'recording_path'    => $relativePath,
            'transcript_status' => 'done',
            'transcript'        => 'Already transcribed text.',
        ]);

        Http::fake();

        // Run job without force
        (new TranscribeCallJob($call->id, false))->handle();

        // Ensure no external HTTP requests were sent
        Http::assertNothingSent();
        $this->assertEquals('Already transcribed text.', $call->fresh()->transcript);
    }
}
