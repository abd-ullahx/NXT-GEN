<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutlookController extends Controller
{
    private OutlookService $outlook;

    public function __construct(OutlookService $outlook)
    {
        $this->outlook = $outlook;
    }

    /**
     * Return the OAuth2 authorization URL for the frontend to redirect to.
     */
    public function authUrl(Request $request): JsonResponse
    {
        try {
            $customUri = $request->query('redirect_uri');
            $url = $this->outlook->getAuthUrl($customUri);
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle the OAuth2 callback from Microsoft.
     * Exchanges the code for tokens, then redirects to the CRM panel.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code      = $request->query('code');
        $customUri = $request->query('redirect_uri');

        if (!$code) {
            $error = $request->query('error_description', 'Authorization failed');
            $frontendUrl = rtrim(env('FRONTEND_URL') ?: (config('app.url') . '/crm'), '/');
            return redirect("{$frontendUrl}/app/outlook?error=" . urlencode($error));
        }

        try {
            $this->outlook->exchangeCodeForTokens($code, $customUri);

            // Trigger an initial sync
            $this->outlook->syncEmails(30);

            $frontendUrl = rtrim(env('FRONTEND_URL') ?: (config('app.url') . '/crm'), '/');
            return redirect("{$frontendUrl}/app/outlook?connected=true");
        } catch (\Exception $e) {
            $frontendUrl = rtrim(env('FRONTEND_URL') ?: (config('app.url') . '/crm'), '/');
            return redirect("{$frontendUrl}/app/outlook?error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Return current connection status.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->outlook->getConnectionStatus());
    }

    /**
     * Disconnect Outlook.
     */
    public function disconnect(): JsonResponse
    {
        $this->outlook->disconnect();
        return response()->json(['message' => 'Outlook disconnected']);
    }

    /**
     * Return paginated, locally-stored emails.
     */
    public function emails(Request $request): JsonResponse
    {
        $page     = (int) $request->query('page', 1);
        $perPage  = (int) $request->query('per_page', 30);
        $search   = $request->query('search', '');
        $filter   = $request->query('filter', 'all'); // all | inbound | outbound | unread

        $query = Email::query()->orderBy('received_at', 'desc')->orderBy('id', 'desc');

        if ($filter === 'inbound') {
            $query->where('direction', 'inbound');
        } elseif ($filter === 'outbound') {
            $query->where('direction', 'outbound');
        } elseif ($filter === 'unread') {
            $query->where('is_read', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                  ->orWhere('from_email', 'LIKE', "%{$search}%")
                  ->orWhere('from_name', 'LIKE', "%{$search}%")
                  ->orWhere('body_preview', 'LIKE', "%{$search}%");
            });
        }

        $total  = $query->count();
        $emails = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data'    => $emails->map(fn($e) => [
                'id'             => $e->id,
                'messageId'      => $e->message_id,
                'direction'      => $e->direction,
                'fromEmail'      => $e->from_email,
                'fromName'       => $e->from_name,
                'toEmail'        => $e->to_email,
                'subject'        => $e->subject,
                'bodyPreview'    => $e->body_preview,
                'bodyHtml'       => $e->body_html,
                'isRead'         => $e->is_read,
                'receivedAt'     => $e->received_at ? \Carbon\Carbon::parse($e->received_at)->toIso8601String() : null,
                'leadId'         => $e->lead_id,
                'createdAt'      => $e->created_at ? \Carbon\Carbon::parse($e->created_at)->toIso8601String() : null,
            ]),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Trigger a manual email sync from Outlook.
     */
    public function sync(): JsonResponse
    {
        try {
            $count = $this->outlook->syncEmails(50);
            return response()->json([
                'message' => "{$count} new emails synced",
                'synced'  => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send an email through Outlook.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string|max:500',
            'body'    => 'required|string',
        ]);

        try {
            $to      = $request->input('to');
            $subject = $request->input('subject');
            $body    = $request->input('body');

            $token = $this->outlook->getValidToken();
            if ($token) {
                // Send via Outlook (Microsoft Graph API)
                $this->outlook->sendEmail($to, $subject, $body);
                return response()->json(['message' => 'Email sent successfully via Outlook']);
            } else {
                // Fallback to Brevo SMTP
                \Illuminate\Support\Facades\Mail::to($to)->queue(
                    new \App\Mail\RawCustomEmail($subject, $body)
                );

                // Record manual outbound email in local DB
                try {
                    \App\Models\Email::create([
                        'message_id'   => 'brevo-' . uniqid(),
                        'direction'    => 'outbound',
                        'from_email'   => config('mail.from.address', 'info@nextgenrelocation.co.uk'),
                        'from_name'    => config('mail.from.name', 'Next Gen Relocation'),
                        'to_email'     => $to,
                        'subject'      => $subject,
                        'body_preview' => strip_tags(substr($body, 0, 200)),
                        'body_html'    => $body,
                        'is_read'      => true,
                        'received_at'  => now(),
                        'lead_id'      => \App\Models\Lead::where('email', $to)->value('id'),
                    ]);
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::warning("Failed to record manual Brevo email in DB: " . $ex->getMessage());
                }

                return response()->json(['message' => 'Email sent successfully via Brevo SMTP fallback']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Track email opens via a 1x1 tracking pixel.
     * Accurately tracks when any recipient (Client/Lead, Driver, Surveyor, Team Member, Contact)
     * opens any email dispatched from the CRM.
     */
    public function track(Request $request)
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        // 1. Ignore HTTP HEAD requests (some mail server pre-checkers send HEAD)
        if ($request->isMethod('HEAD')) {
            return response($gif, 200)
                ->header('Content-Type', 'image/gif')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $userAgent = strtolower($request->header('User-Agent', ''));
        $isTest = (bool) ($request->query('test') || $request->query('test_open'));

        // 2. Filter out known anti-spam delivery scanners, crawlers, and automated bots.
        // NOTE: googleimageproxy and appleimageproxy are intentionally allowed because they represent
        // genuine human opens from Gmail and Apple Mail / iOS Mail apps.
        $botPatterns = [
            'bot', 'spider', 'crawler', 'wget', 'python', 'guzzle',
            'barracuda', 'proofpoint', 'mimecast', 'microsoft office', 'office365',
            'defender', 'outlook-express', 'applenewsbot', 'yahoo! slurp',
            'bingbot', 'phantomjs', 'headless'
        ];

        if (!$isTest) {
            $botPatterns[] = 'curl';
            foreach ($botPatterns as $pattern) {
                if (str_contains($userAgent, $pattern)) {
                    \Illuminate\Support\Facades\Log::info("Tracking pixel ignored bot/scanner [{$pattern}]: {$userAgent}");
                    return response($gif, 200)
                        ->header('Content-Type', 'image/gif')
                        ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
                }
            }
        }

        $leadId         = $request->query('lead_id');
        $recipientEmail = strtolower(trim($request->query('recipient_email', '')));
        $recipientName  = trim($request->query('recipient_name', ''));
        $recipientType  = strtolower(trim($request->query('recipient_type', '')));
        $emailType      = trim($request->query('email_type') ?: ($request->query('subject') ?: 'Email'));
        $quoteNumber    = trim($request->query('quote_number', ''));

        // Clean up emailType if it's long or has artifacts
        if (strlen($emailType) > 80) {
            $emailType = substr($emailType, 0, 77) . '...';
        }

        try {
            // Determine recipient details if not provided
            $lead = null;
            if ($leadId) {
                $lead = \App\Models\Lead::find($leadId);
            }

            if ($recipientEmail && empty($recipientType)) {
                $user = \App\Models\User::where('email', $recipientEmail)->first();
                if ($user) {
                    $recipientName = $recipientName ?: $user->name;
                    $userRole = strtolower($user->role ?: '');
                    if (in_array($userRole, ['driver', 'surveyor'])) {
                        $recipientType = $userRole;
                    } else {
                        $recipientType = 'team_member';
                    }
                } elseif ($lead && strtolower($lead->email) === $recipientEmail) {
                    $recipientType = 'client';
                    $recipientName = $recipientName ?: $lead->name;
                } else {
                    $potentialLead = \App\Models\Lead::where('email', $recipientEmail)->first();
                    if ($potentialLead) {
                        $lead = $potentialLead;
                        $leadId = $potentialLead->id;
                        $recipientType = 'client';
                        $recipientName = $recipientName ?: $potentialLead->name;
                    }
                }
            }

            if (empty($recipientType)) {
                if ($lead) {
                    $recipientType = 'client';
                    $recipientName = $recipientName ?: $lead->name;
                } else {
                    $recipientType = 'recipient';
                }
            }

            if (empty($recipientName)) {
                $recipientName = $lead?->name ?: ($recipientEmail ?: 'Recipient');
            }

            // Build tailored notification for CRM UI
            $title = "Email Opened 📧";
            $message = "{$recipientName} opened the {$emailType}.";
            $link = "/crm/app/leads";

            switch ($recipientType) {
                case 'driver':
                    $title = "Driver {$recipientName} Opened Email 🚚";
                    $message = "Driver {$recipientName} opened \"{$emailType}\".";
                    $link = $leadId ? "/crm/app/jobs?view={$leadId}" : "/crm/app/jobs";
                    break;

                case 'surveyor':
                    $title = "Surveyor {$recipientName} Opened Email 📋";
                    $message = "Surveyor {$recipientName} opened \"{$emailType}\".";
                    $link = $leadId ? "/crm/app/leads?view={$leadId}" : "/crm/app/calendar";
                    break;

                case 'team_member':
                case 'staff':
                case 'admin':
                    $title = "Team Member {$recipientName} Opened Email 👥";
                    $message = "Team member {$recipientName} opened \"{$emailType}\".";
                    $link = "/crm/app/team";
                    break;

                case 'client':
                case 'lead':
                    $title = "Client {$recipientName} Opened Email 📧";
                    $quoteInfo = $quoteNumber ? " ({$quoteNumber})" : "";
                    $message = "Client {$recipientName} opened {$emailType}{$quoteInfo}.";
                    $link = $leadId ? "/crm/app/leads?view={$leadId}" : "/crm/app/leads";
                    break;

                default:
                    $title = "Email Opened 📧";
                    $message = "{$recipientName} opened \"{$emailType}\".";
                    $link = "/crm/app/outlook";
                    break;
            }

            // 3. Debounce: prevent duplicate notification spam for identical email open within 3 minutes
            // (0 debounce for testing)
            $cacheKey = 'email_opened_' . md5(($recipientEmail ?: ($leadId ?: 'unknown')) . '_' . $emailType . '_' . $recipientType);
            $debounceMinutes = $isTest ? 0 : 3;

            if ($debounceMinutes === 0 || !\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                if ($debounceMinutes > 0) {
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addMinutes($debounceMinutes));
                }

                \App\Models\AppNotification::create([
                    'user_id' => null, // broadcasts to all admin/staff in CRM
                    'type'    => 'info',
                    'title'   => $title,
                    'message' => $message,
                    'link'    => $link,
                    'is_read' => false,
                ]);

                // Update opened_at on matching outbound email record in database if exists
                try {
                    $emailQuery = \App\Models\Email::where('direction', 'outbound');
                    if ($recipientEmail) {
                        $emailQuery->where('to_email', $recipientEmail);
                    } elseif ($leadId) {
                        $emailQuery->where('lead_id', $leadId);
                    }
                    $matchedEmail = $emailQuery->latest('created_at')->first();
                    if ($matchedEmail && !$matchedEmail->opened_at) {
                        $matchedEmail->update([
                            'opened_at' => now(),
                            'is_read'   => true,
                        ]);
                    }
                } catch (\Throwable $ex) {
                    // Non-fatal
                }

                \Illuminate\Support\Facades\Log::info("Genuine email open tracked: [{$recipientType}] {$recipientName} ({$recipientEmail}) opened {$emailType}");
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Tracking pixel processing error: " . $e->getMessage());
        }

        return response($gif, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Click tracking endpoint for email links.
     * Triggers an instant notification when a recipient clicks a link inside an email.
     */
    public function trackClick(Request $request)
    {
        $leadId = $request->query('lead_id');
        $label  = $request->query('label', 'Email Link');
        $target = $request->query('target', env('FRONTEND_URL', rtrim(config('app.url') . '/crm', '/')));

        if ($leadId) {
            try {
                $lead = \App\Models\Lead::find($leadId);
                if ($lead) {
                    \App\Models\AppNotification::create([
                        'user_id' => null,
                        'type'    => 'info',
                        'title'   => 'Email Link Clicked 🔗',
                        'message' => "Client {$lead->name} clicked link: \"{$label}\".",
                        'link'    => '/crm/app/leads?view=' . $lead->id,
                        'is_read' => false,
                    ]);
                    \Illuminate\Support\Facades\Log::info("Link clicked by lead {$lead->id} ({$lead->name}): {$label}");
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Click tracking error: " . $e->getMessage());
            }
        }

        return redirect()->away($target);
    }
}
