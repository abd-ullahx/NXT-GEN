<?php

namespace Tests\Feature;

use App\Jobs\DownloadRecordingJob;
use App\Models\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CallWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $authToken = 'test_twilio_secret_token_123';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.twilio.auth_token', $this->authToken);
    }

    /**
     * Compute valid X-Twilio-Signature for testing.
     */
    private function generateSignature(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k . $v;
        }
        return base64_encode(hash_hmac('sha1', $data, $this->authToken, true));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = [
            'CallSid'           => 'CA111222333',
            'RecordingUrl'      => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE123',
            'RecordingDuration' => '45',
            'From'              => '+447700900111',
            'To'                => '+447700900222',
        ];

        // Send with invalid signature
        $response = $this->withHeaders([
            'X-Twilio-Signature' => 'invalid_signature_hash',
        ])->post('/api/webhooks/twilio/recording', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('calls', ['provider_call_sid' => 'CA111222333']);
    }

    public function test_valid_signature_completed_recording_dispatches_jobs(): void
    {
        Queue::fake();

        $payload = [
            'CallDuration'      => '60',
            'CallSid'           => 'CA999888777',
            'Direction'         => 'inbound',
            'From'              => '+447700900111',
            'RecordingDuration' => '58',
            'RecordingStatus'   => 'completed',
            'RecordingUrl'      => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE999',
            'To'                => '+447700900222',
        ];

        $url = url('/api/webhooks/twilio/recording');
        $validSignature = $this->generateSignature($url, $payload);

        $response = $this->withHeaders([
            'X-Twilio-Signature' => $validSignature,
        ])->post('/api/webhooks/twilio/recording', $payload);

        $response->assertStatus(200);

        // Verify Call record was created
        $this->assertDatabaseHas('calls', [
            'provider_call_sid' => 'CA999888777',
            'recording_url'     => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE999',
            'duration_seconds'  => 58,
            'direction'         => 'inbound',
            'transcript_status' => 'pending',
        ]);

        $call = Call::where('provider_call_sid', 'CA999888777')->firstOrFail();

        // Verify DownloadRecordingJob was dispatched
        Queue::assertPushed(DownloadRecordingJob::class, function ($job) use ($call) {
            return $job->callId === $call->id && $job->recordingUrl === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE999';
        });
    }

    public function test_duplicate_webhooks_are_idempotent(): void
    {
        Queue::fake();

        $payload = [
            'CallSid'           => 'CA_IDEMPOTENT_1',
            'RecordingUrl'      => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE_IDEM',
            'RecordingDuration' => '30',
            'From'              => '+447700900111',
            'To'                => '+447700900222',
        ];

        $url = url('/api/webhooks/twilio/recording');
        $validSignature = $this->generateSignature($url, $payload);

        // First webhook post
        $res1 = $this->withHeaders(['X-Twilio-Signature' => $validSignature])
            ->post('/api/webhooks/twilio/recording', $payload);
        $res1->assertStatus(200);

        // Second webhook post with same CallSid
        $res2 = $this->withHeaders(['X-Twilio-Signature' => $validSignature])
            ->post('/api/webhooks/twilio/recording', $payload);
        $res2->assertStatus(200);

        // Assert only ONE record exists
        $this->assertEquals(1, Call::where('provider_call_sid', 'CA_IDEMPOTENT_1')->count());
    }
}
