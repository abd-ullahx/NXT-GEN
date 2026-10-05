<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $serviceAccountPath;
    private string $projectId;

    public function __construct()
    {
        $this->serviceAccountPath = storage_path('app/firebase-service-account.json');
        
        if (file_exists($this->serviceAccountPath)) {
            $json = json_decode(file_get_contents($this->serviceAccountPath), true);
            $this->projectId = $json['project_id'] ?? '';
        } else {
            $this->projectId = '';
        }
    }

    /**
     * Retrieve OAuth2 Access Token with caching.
     */
    public function getAccessToken(): string
    {
        if (!file_exists($this->serviceAccountPath)) {
            Log::warning('FCM Service Account JSON not found.');
            return '';
        }

        return Cache::remember('fcm_access_token', 3300, function () {
            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = new ServiceAccountCredentials($scopes, $this->serviceAccountPath);
            $token = $credentials->fetchAuthToken();
            return $token['access_token'] ?? '';
        });
    }

    /**
     * Send Push Notification to a specific device.
     */
    public function sendToDevice(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        if (empty($deviceToken)) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken) || empty($this->projectId)) {
            Log::error('FCM credentials missing or invalid.');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Convert data array values to strings as FCM requires string values in data payload
        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[$key] = (string) $value;
        }

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => (object) $stringData,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'nextgen_notifications',
                        'sound' => 'default',
                        'notification_priority' => 'PRIORITY_MAX',
                        'default_vibrate_timings' => true,
                        'default_light_settings' => true,
                    ]
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                            'badge' => 1
                        ]
                    ]
                ]
            ],
        ];

        try {
            $response = Http::withToken($accessToken)->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            $errorBody = $response->json();
            $errorCode = $errorBody['error']['details'][0]['errorCode'] ?? null;
            $status = $response->status();

            if ($status === 404 || $errorCode === 'UNREGISTERED') {
                // Auto-prune stale tokens
                \App\Models\User::where('fcm_token', $deviceToken)->update([
                    'fcm_token' => null,
                    'device_type' => null
                ]);
                Log::info("FCM Token auto-pruned for UNREGISTERED/404 error.");
            } else {
                Log::warning('FCM Push Failed: ' . $response->body());
            }
            
            return false;
        } catch (\Throwable $e) {
            Log::error('FCM Push Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Data-only Silent Push
     */
    public function sendDataOnlyMessage(string $deviceToken, array $data = []): bool
    {
        if (empty($deviceToken)) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken) || empty($this->projectId)) {
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Convert data array values to strings
        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[$key] = (string) $value;
        }

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'data' => (object) $stringData,
            ],
        ];

        try {
            $response = Http::withToken($accessToken)->post($url, $payload);
            
            if ($response->successful()) {
                return true;
            }

            $errorBody = $response->json();
            $errorCode = $errorBody['error']['details'][0]['errorCode'] ?? null;
            $status = $response->status();

            if ($status === 404 || $errorCode === 'UNREGISTERED') {
                \App\Models\User::where('fcm_token', $deviceToken)->update([
                    'fcm_token' => null,
                    'device_type' => null
                ]);
            }
            
            return false;
        } catch (\Throwable $e) {
            Log::error('FCM Data Push Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send High-Priority VoIP Data-Only Push for Incoming Calls.
     * Android Doze mode and iOS background wakeups require data-only payload + high priority.
     */
    public function sendIncomingCallPush(string $deviceToken, array $callData): bool
    {
        if (empty($deviceToken)) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken) || empty($this->projectId)) {
            Log::error('FCM credentials missing or invalid for incoming call push.');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $stringData = [
            'type'          => 'incoming_call',
            'call_id'       => (string) ($callData['call_id'] ?? ''),
            'room_id'       => (string) ($callData['room_id'] ?? ''),
            'caller_id'     => (string) ($callData['caller_id'] ?? ''),
            'caller_name'   => (string) ($callData['caller_name'] ?? 'Admin Office'),
            'caller_email'  => (string) ($callData['caller_email'] ?? 'admin@nextgenrelocation.co.uk'),
            'caller_role'   => (string) ($callData['caller_role'] ?? 'admin'),
            'is_video'      => !empty($callData['is_video']) && $callData['is_video'] !== 'false' ? 'true' : 'false',
            'status'        => (string) ($callData['status'] ?? 'ringing'),
            'zego_app_id'   => (string) ($callData['zego_app_id'] ?? config('services.zego.app_id', '949748271')),
            'zego_app_sign' => (string) ($callData['zego_app_sign'] ?? config('services.zego.app_sign', '')),
        ];

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'android' => [
                    'priority' => 'high',
                    'ttl'      => '45s',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority'  => '10',
                        'apns-push-type' => 'background',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                        ],
                    ],
                ],
                'data' => (object) $stringData,
            ],
        ];

        try {
            $response = Http::withToken($accessToken)->post($url, $payload);
            if ($response->successful()) {
                Log::info("FCM Incoming Call Push successfully dispatched to call #{$stringData['call_id']}");
                return true;
            }

            $errorBody = $response->json();
            $errorCode = $errorBody['error']['details'][0]['errorCode'] ?? null;
            $status = $response->status();

            if ($status === 404 || $errorCode === 'UNREGISTERED') {
                \App\Models\User::where('fcm_token', $deviceToken)->update([
                    'fcm_token'   => null,
                    'device_type' => null,
                ]);
                Log::info("FCM Token auto-pruned during incoming call push (UNREGISTERED).");
            } else {
                Log::warning('FCM Incoming Call Push Failed: ' . $response->body());
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM Incoming Call Push Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send High-Priority VoIP Data-Only Push for Cancelled / Timed-out Calls.
     * Tells the mobile app to dismiss the ringing screen immediately.
     */
    public function sendCallCancelledPush(string $deviceToken, int $callId, string $status = 'cancelled'): bool
    {
        if (empty($deviceToken)) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken) || empty($this->projectId)) {
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'android' => [
                    'priority' => 'high',
                ],
                'data' => (object) [
                    'type'    => 'call_cancelled',
                    'call_id' => (string) $callId,
                    'status'  => (string) $status,
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)->post($url, $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FCM Call Cancelled Push Exception: ' . $e->getMessage());
            return false;
        }
    }
}
