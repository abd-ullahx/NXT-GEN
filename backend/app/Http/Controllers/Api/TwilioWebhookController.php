<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadRecordingJob;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    /**
     * Handle incoming Twilio RecordingStatusCallback webhook.
     */
    public function handleRecordingCallback(Request $request): Response
    {
        $authToken = config('services.twilio.auth_token');

        // Verify Twilio Signature if auth token is configured
        if (!empty($authToken) && !$this->isValidTwilioSignature($request, $authToken)) {
            Log::warning('[TwilioWebhook] Rejected webhook request: Invalid signature.');
            return response('<Response><Reject reason="unauthorized"/></Response>', 403, [
                'Content-Type' => 'text/xml',
            ]);
        }

        $callSid = $request->input('CallSid');
        $recordingUrl = $request->input('RecordingUrl');
        $recordingDuration = (int) ($request->input('RecordingDuration') ?: $request->input('CallDuration') ?: 0);
        $from = $request->input('From');
        $to = $request->input('To');
        $direction = strtolower($request->input('Direction', 'inbound'));

        if (empty($callSid) || empty($recordingUrl)) {
            return response('<Response><Error>Missing CallSid or RecordingUrl</Error></Response>', 400, [
                'Content-Type' => 'text/xml',
            ]);
        }

        // Idempotent find or create call record
        $call = Call::firstOrCreate(
            ['provider_call_sid' => $callSid],
            [
                'direction'         => str_contains($direction, 'outbound') ? 'outbound' : 'inbound',
                'from_number'       => $from,
                'to_number'         => $to,
                'started_at'        => now()->subSeconds($recordingDuration),
                'transcript_status' => 'pending',
            ]
        );

        // Update with recording details
        $call->update([
            'recording_url'     => $recordingUrl,
            'duration_seconds'  => $recordingDuration ?: $call->duration_seconds,
            'ended_at'          => now(),
        ]);

        // Dispatch queued download job (idempotent)
        DownloadRecordingJob::dispatch($call->id, $recordingUrl);

        Log::info("[TwilioWebhook] Accepted recording for Call SID {$callSid}. Download job dispatched.");

        return response('<Response></Response>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    /**
     * Verify X-Twilio-Signature against the request URL and POST parameters.
     */
    protected function isValidTwilioSignature(Request $request, string $authToken): bool
    {
        $signature = $request->header('X-Twilio-Signature');
        if (empty($signature)) {
            return false;
        }

        // Build data string: full URL + sorted POST key-value pairs
        $url = $request->fullUrl();
        $postData = $request->post();
        ksort($postData);

        $data = $url;
        foreach ($postData as $key => $value) {
            $data .= $key . $value;
        }

        $expectedSignature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expectedSignature, $signature);
    }
}
