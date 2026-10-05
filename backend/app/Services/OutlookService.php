<?php

namespace App\Services;

use App\Models\OutlookToken;
use App\Models\Email;
use App\Models\Lead;
use App\Models\Contact;
use App\Services\LeadEmailParser;
use App\Services\AutomationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OutlookService
{
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private string $redirectUri;
    private string $graphBaseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct()
    {
        $this->clientId     = config('services.microsoft.client_id');
        $this->clientSecret = config('services.microsoft.client_secret');
        $this->tenantId     = config('services.microsoft.tenant_id');
        $this->redirectUri  = config('services.microsoft.redirect_uri');
    }

    /**
     * Build the OAuth2 authorization URL for Microsoft login.
     */
    public function getAuthUrl(?string $customRedirectUri = null): string
    {
        if (empty($this->clientId)) {
            throw new \Exception("MICROSOFT_CLIENT_ID is not configured in backend/.env file.");
        }

        $redirectUri = $customRedirectUri ?: $this->redirectUri;

        $params = http_build_query([
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'response_mode' => 'query',
            'scope'         => 'openid profile email offline_access Mail.Read Mail.ReadWrite Mail.Send User.Read',
            'state'         => bin2hex(random_bytes(16)),
        ]);

        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?{$params}";
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     */
    public function exchangeCodeForTokens(string $code, ?string $customRedirectUri = null): OutlookToken
    {
        $redirectUri = $customRedirectUri ?: $this->redirectUri;

        $response = Http::withoutVerifying()->asForm()->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
                'scope'         => 'openid profile email offline_access Mail.Read Mail.ReadWrite Mail.Send User.Read',
            ]
        );

        if ($response->failed()) {
            Log::error('Outlook token exchange failed', ['body' => $response->body()]);
            throw new \Exception('Failed to exchange authorization code: ' . $response->body());
        }

        $data = $response->json();

        // Fetch the user's profile to get their email
        $profile = $this->fetchProfile($data['access_token']);
        $userEmail = $profile['mail'] ?? $profile['userPrincipalName'];

        Log::info('Outlook token successfully exchanged & stored for user', [
            'user'          => $userEmail,
            'access_token'  => substr($data['access_token'], 0, 25) . '...',
            'has_refresh'   => !empty($data['refresh_token']),
            'expires_in'    => $data['expires_in'] ?? null,
        ]);

        // Store or update the token
        $token = OutlookToken::updateOrCreate(
            ['user_email' => $userEmail],
            [
                'display_name'  => $profile['displayName'] ?? null,
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at'    => Carbon::now()->addSeconds($data['expires_in'] - 60),
            ]
        );

        return $token;
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(OutlookToken $token): OutlookToken
    {
        $response = Http::withoutVerifying()->asForm()->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $token->refresh_token,
                'grant_type'    => 'refresh_token',
                'scope'         => 'openid profile email offline_access Mail.Read Mail.ReadWrite Mail.Send User.Read',
            ]
        );

        if ($response->failed()) {
            $errBody = $response->body();
            Log::error('Outlook token refresh failed', ['status' => $response->status(), 'body' => $errBody]);
            throw new \Exception('Failed to refresh token: ' . $errBody);
        }

        $data = $response->json();

        $token->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at'    => Carbon::now()->addSeconds($data['expires_in'] - 60),
        ]);

        return $token->fresh();
    }

    /**
     * Get a valid access token, refreshing if needed.
     */
    public function getValidToken(): ?OutlookToken
    {
        $token = OutlookToken::latest('updated_at')->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpired()) {
            try {
                $token = $this->refreshToken($token);
            } catch (\Exception $e) {
                Log::error('Automatic token refresh failed in getValidToken: ' . $e->getMessage());
                // Delete invalid token so user is prompted to reconnect rather than stuck in loop
                $token->delete();
                return null;
            }
        }

        return $token;
    }

    /**
     * Fetch user profile from Microsoft Graph.
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = Http::withoutVerifying()
            ->withToken($accessToken)
            ->get("{$this->graphBaseUrl}/me");

        if ($response->failed()) {
            throw new \Exception('Failed to fetch Microsoft profile');
        }

        return $response->json();
    }

    /**
     * Fetch emails from Microsoft Graph.
     */
    public function fetchEmails(int $top = 15, int $skip = 0, string $folder = ''): array
    {
        $token = $this->getValidToken();

        if (!$token) {
            throw new \Exception('Outlook not connected');
        }

        $url = $folder 
            ? "{$this->graphBaseUrl}/me/mailFolders/{$folder}/messages"
            : "{$this->graphBaseUrl}/me/messages";

        $response = Http::withoutVerifying()
            ->withToken($token->access_token)
            ->get($url, [
                '$top'     => $top,
                '$skip'    => $skip,
                '$orderby' => 'receivedDateTime desc',
                '$select'  => 'id,conversationId,subject,bodyPreview,body,from,toRecipients,isRead,receivedDateTime,isDraft',
            ]);

        if ($response->status() === 401 && !empty($token->refresh_token)) {
            try {
                $token = $this->refreshToken($token);
                $response = Http::withoutVerifying()
                    ->withToken($token->access_token)
                    ->get($url, [
                        '$top'     => $top,
                        '$skip'    => $skip,
                        '$orderby' => 'receivedDateTime desc',
                        '$select'  => 'id,conversationId,subject,bodyPreview,body,from,toRecipients,isRead,receivedDateTime,isDraft',
                    ]);
            } catch (\Exception $e) {
                Log::warning("Auto token refresh failed during fetchEmails: " . $e->getMessage());
            }
        }

        if ($response->failed()) {
            $status = $response->status();
            $errorBody = $response->body();
            $wwwAuth = $response->header('WWW-Authenticate');
            Log::error('Failed to fetch Outlook emails', ['status' => $status, 'body' => $errorBody, 'www_authenticate' => $wwwAuth]);
            $errMsg = json_decode($errorBody, true)['error']['message'] ?? ($errorBody ?: $wwwAuth ?: "HTTP {$status} error");
            throw new \Exception('Failed to fetch emails from Microsoft Graph: ' . $errMsg);
        }

        return $response->json();
    }

    /**
     * Sync emails from Outlook into the local database.
     * Returns the count of newly synced emails.
     */
    /**
     * Sync emails from Outlook into the local database.
     * Returns the count of newly synced emails.
     */
    public function syncEmails(int $count = 50): int
    {
        $token = $this->getValidToken();
        if (!$token) {
            return 0;
        }
        $userEmail = $token->user_email;
        $synced = 0;

        // 1. Sync from Inbox
        try {
            $inboxData = $this->fetchEmails($count, 0, 'inbox');
            $inboxEmails = $inboxData['value'] ?? [];
            $synced += $this->processSyncedEmails($inboxEmails, $userEmail, 'inbound');
        } catch (\Throwable $e) {
            Log::warning("syncEmails inbox error: " . $e->getMessage());
        }

        // 2. Sync from Sent Items
        try {
            $sentData = $this->fetchEmails($count, 0, 'sentitems');
            $sentEmails = $sentData['value'] ?? [];
            $synced += $this->processSyncedEmails($sentEmails, $userEmail, 'outbound');
        } catch (\Throwable $e) {
            Log::warning("syncEmails sentitems error: " . $e->getMessage());
        }

        return $synced;
    }

    /**
     * Helper to process fetched Microsoft Graph email array and save to local DB.
     */
    private function processSyncedEmails(array $emails, string $userEmail, string $defaultDirection): int
    {
        $synced = 0;
        foreach ($emails as $msg) {
            if ($msg['isDraft'] ?? false) {
                continue;
            }

            $messageId = $msg['id'];
            $fromEmail = $msg['from']['emailAddress']['address'] ?? '';
            $fromName  = $msg['from']['emailAddress']['name'] ?? '';
            $toEmail   = '';

            if (!empty($msg['toRecipients'])) {
                $toEmail = $msg['toRecipients'][0]['emailAddress']['address'] ?? '';
            }

            // Determine direction
            $direction = strtolower($fromEmail) === strtolower($userEmail) ? 'outbound' : 'inbound';
            if ($defaultDirection === 'outbound') {
                $direction = 'outbound';
            }

            // Check if this specific email message was previously deleted by the user
            $isDeleted = \Illuminate\Support\Facades\DB::table('deleted_leads')
                ->where('message_id', 'LIKE', "%{$messageId}%")
                ->exists();

            if ($isDeleted) {
                continue;
            }

            // Check if already synced in local emails table
            $exists = Email::where('message_id', $messageId)->exists();
            if ($exists) {
                continue;
            }

            // Try to parse inbound email as lead-provider / inquiry notification first
            $parsed = $direction === 'inbound' ? app(LeadEmailParser::class)->parse($msg) : null;
            $leadId = null;

            if ($parsed) {
                $newLead = Lead::create(array_merge($parsed, [
                    'id'       => 'L-' . mt_rand(100000, 99999999),
                    'status'   => 'draft',
                    'stage'    => 'New',
                    'ai_score' => 5,
                    'priority' => 'Warm',
                ]));
                $leadId = $newLead->id;

                try {
                    Contact::updateOrCreate(
                        ['email' => $newLead->email],
                        [
                            'id'             => 'C-' . mt_rand(100000, 99999999),
                            'name'           => $newLead->name,
                            'email'          => $newLead->email,
                            'phone'          => $newLead->phone,
                            'type'           => 'Customer',
                            'moves'          => 1,
                            'lifetime_value' => $newLead->est_value ?: 1500,
                            'status'         => 'Active',
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning("Failed to auto-create contact from Outlook lead {$leadId}: " . $e->getMessage());
                }

                Log::info('Auto-created DRAFT lead from provider email', [
                    'source'  => $parsed['source'],
                    'name'    => $parsed['name'],
                    'email'   => $parsed['email'],
                    'lead_id' => $leadId,
                ]);
            }

            Email::create([
                'message_id'      => $messageId,
                'conversation_id' => $msg['conversationId'] ?? null,
                'direction'       => $direction,
                'from_email'      => $fromEmail,
                'from_name'       => $fromName,
                'to_email'        => $toEmail,
                'subject'         => $msg['subject'] ?? '(No Subject)',
                'body_preview'    => $msg['bodyPreview'] ?? '',
                'body_html'       => $msg['body']['content'] ?? '',
                'is_read'         => (bool) ($msg['isRead'] ?? false),
                'received_at'     => Carbon::parse($msg['receivedDateTime']),
                'lead_id'         => $leadId,
            ]);

            // Inbound reply detection: if this is an inbound email from a lead
            // that is currently awaiting a response (reminder cycle armed), treat
            // the reply as the customer's response and stop the reminders.
            if ($direction === 'inbound' && !$parsed && $fromEmail) {
                $waiting = Lead::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])
                    ->whereNotNull('reminder_context')
                    ->whereNull('last_response_at')
                    ->first();
                if ($waiting) {
                    try {
                        app(AutomationService::class)->recordResponse($waiting, $waiting->reminder_context);
                        Log::info("Inbound reply from {$fromEmail} recorded as response for lead {$waiting->id}.");
                    } catch (\Throwable $e) {
                        Log::warning("Failed to record inbound reply response: " . $e->getMessage());
                    }
                }
            }

            $synced++;
        }

        return $synced;
    }

    /**
     * Send an email via Microsoft Graph.
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $replyToMessageId = null): array
    {
        $token = $this->getValidToken();

        if (!$token) {
            throw new \Exception('Outlook not connected. Please connect your account first.');
        }

        // Inject tracking pixel if not already present in HTML body
        if (!str_contains($body, '/api/outlook/track')) {
            $baseUrl = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');
            $user = \App\Models\User::where('email', $to)->first();
            $lead = \App\Models\Lead::where('email', $to)->first();
            $role = $user ? (in_array(strtolower($user->role), ['driver', 'surveyor']) ? strtolower($user->role) : 'team_member') : ($lead ? 'client' : 'recipient');
            $name = $user?->name ?: ($lead?->name ?: $to);
            $pixelUrl = "{$baseUrl}/api/outlook/track?" . http_build_query([
                'recipient_email' => $to,
                'recipient_name'  => $name,
                'recipient_type'  => $role,
                'email_type'      => $subject,
                'lead_id'         => $lead?->id,
                't'               => time(),
            ]);
            $pixelTag = '<img src="' . htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:none;width:1px;height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;" />';
            if (str_contains($body, '</body>')) {
                $body = str_replace('</body>', $pixelTag . '</body>', $body);
            } else {
                $body .= $pixelTag;
            }
        }

        $mailPayload = [
            'message' => [
                'subject' => $subject,
                'body'    => [
                    'contentType' => 'HTML',
                    'content'     => $body,
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to,
                        ],
                    ],
                ],
            ],
            'saveToSentItems' => true,
        ];

        // Execute HTTP request to Microsoft Graph sendMail with automatic retry for transient network glitches
        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = Http::withoutVerifying()
                    ->withToken($token->access_token)
                    ->post("{$this->graphBaseUrl}/me/sendMail", $mailPayload);

                // If token expired (401), try refreshing token automatically and retry once
                if ($response->status() === 401 && !empty($token->refresh_token)) {
                    try {
                        $token = $this->refreshToken($token);
                        $response = Http::withoutVerifying()
                            ->withToken($token->access_token)
                            ->post("{$this->graphBaseUrl}/me/sendMail", $mailPayload);
                    } catch (\Exception $e) {
                        Log::warning("Auto token refresh failed during sendEmail: " . $e->getMessage());
                    }
                }
                break;
            } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                Log::warning("Transient network error during sendMail attempt {$attempt}/{$maxAttempts}: " . $ce->getMessage());
                if ($attempt >= $maxAttempts) {
                    throw $ce;
                }
                usleep(500000); // Wait 500ms before retry
            }
        }

        // Graph returns 202 Accepted when the message is queued for delivery.
        Log::info('Outlook sendMail response', [
            'status'    => $response->status(),
            'to'        => $to,
            'from'      => $token->user_email,
            'accepted'  => $response->status() === 202,
        ]);

        if ($response->failed()) {
            $status = $response->status();
            $errorBody = $response->body();
            $wwwAuth = $response->header('WWW-Authenticate');
            Log::error('Failed to send email via Outlook', [
                'status' => $status,
                'body'   => $errorBody,
                'www_authenticate' => $wwwAuth,
            ]);

            $errMsg = json_decode($errorBody, true)['error']['message'] ?? ($errorBody ?: $wwwAuth ?: "HTTP {$status} error");

            if ($status === 403) {
                throw new \Exception('Microsoft Graph permission denied (403): ' . $errMsg);
            }

            if ($status === 401) {
                throw new \Exception('Microsoft Graph Unauthorized (401): ' . $errMsg);
            }

            throw new \Exception('Failed to send email: ' . $errMsg);
        }

        // Save the sent email locally
        Email::create([
            'message_id'      => 'sent-' . uniqid(),
            'direction'       => 'outbound',
            'from_email'      => $token->user_email,
            'from_name'       => $token->display_name,
            'to_email'        => $to,
            'subject'         => $subject,
            'body_preview'    => strip_tags(substr($body, 0, 200)),
            'body_html'       => $body,
            'is_read'         => true,
            'received_at'     => Carbon::now(),
            'lead_id'         => Lead::where('email', $to)->value('id'),
        ]);

        return ['success' => true, 'message' => 'Email sent successfully'];
    }

    /**
     * Check if Outlook is connected.
     */
    public function getConnectionStatus(): array
    {
        $token = OutlookToken::latest('updated_at')->first();

        if (!$token) {
            return [
                'connected'   => false,
                'email'       => null,
                'displayName' => null,
            ];
        }

        return [
            'connected'   => true,
            'email'       => $token->user_email,
            'displayName' => $token->display_name,
            'expiresAt'   => $token->expires_at->toIso8601String(),
        ];
    }

    /**
     * Disconnect Outlook by removing stored tokens.
     */
    public function disconnect(): void
    {
        OutlookToken::truncate();
    }
}
