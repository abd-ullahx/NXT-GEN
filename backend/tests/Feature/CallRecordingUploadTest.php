<?php

namespace Tests\Feature;

use App\Jobs\TranscribeCallJob;
use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CallRecordingUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Config::set('services.whisper.disk', 'local');

        $this->admin = User::factory()->create([
            'role'  => 'admin',
            'email' => 'admin_upload@test.com',
        ]);
        $this->token = $this->admin->createToken('upload-test')->plainTextToken;
    }

    public function test_upload_recording_stores_file_and_dispatches_job(): void
    {
        Queue::fake();

        $call = Call::create([
            'user_id'           => $this->admin->id,
            'provider_call_sid' => 'upload_test_sid_1',
            'transcript_status' => 'pending',
        ]);

        $fakeAudio = UploadedFile::fake()->create('recorded_call.webm', 256, 'audio/webm');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/calls/{$call->id}/recording", [
                'recording'        => $fakeAudio,
                'duration_seconds' => 45,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'call'    => [
                    'id'               => $call->id,
                    'duration_seconds' => 45,
                    'transcript_status'=> 'pending',
                ],
            ]);

        $call->refresh();
        $this->assertNotEmpty($call->recording_path);
        $this->assertTrue(Storage::disk('local')->exists($call->recording_path));

        Queue::assertPushed(TranscribeCallJob::class, function ($job) use ($call) {
            return $job->callId === $call->id;
        });
    }

    public function test_upload_recording_rejects_disallowed_mime(): void
    {
        $call = Call::create([
            'user_id'           => $this->admin->id,
            'provider_call_sid' => 'upload_test_sid_bad',
            'transcript_status' => 'pending',
        ]);

        $fakeFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/calls/{$call->id}/recording", [
                'recording' => $fakeFile,
            ]);

        $response->assertStatus(422);
    }

    public function test_stream_recording_returns_audio_stream(): void
    {
        $relativePath = 'recordings/2026/09/sample_stream.webm';
        Storage::disk('local')->put($relativePath, 'AUDIO_BYTE_CONTENT');

        $call = Call::create([
            'user_id'           => $this->admin->id,
            'provider_call_sid' => 'stream_test_sid',
            'recording_path'    => $relativePath,
            'transcript_status' => 'done',
        ]);

        $signedUrl = URL::temporarySignedRoute('api.calls.audio', now()->addHour(), ['call' => $call->id]);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('Accept-Ranges', 'bytes');
    }
}
