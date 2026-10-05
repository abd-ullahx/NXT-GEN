<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Lead;
use App\Models\Contact;
use App\Models\Call;
use App\Policies\CallPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production' || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Gate::policy(Call::class, CallPolicy::class);
        // Automatically inject 1x1 open-tracking pixel into EVERY outgoing email sent from CRM
        Event::listen(MessageSending::class, function (MessageSending $event) {
            try {
                $email = $event->message;
                if (!$email instanceof \Symfony\Component\Mime\Email) {
                    return;
                }

                $htmlBody = $email->getHtmlBody();
                if (!$htmlBody || !is_string($htmlBody)) {
                    return;
                }

                // If tracking pixel is already injected, do not duplicate
                if (str_contains($htmlBody, '/api/outlook/track')) {
                    return;
                }

                $toAddresses = $email->getTo();
                if (empty($toAddresses)) {
                    return;
                }

                $primaryTo = $toAddresses[0];
                $recipientEmail = strtolower(trim($primaryTo->getAddress()));
                $recipientName = trim($primaryTo->getName() ?: '');
                $subject = $email->getSubject() ?: 'Email Communication';

                // Determine recipient role & context
                $recipientRole = 'recipient';
                $leadId = null;

                // 1. Check if recipient is a CRM User (driver, surveyor, staff, admin, etc.)
                $user = User::where('email', $recipientEmail)->first();
                if ($user) {
                    if (empty($recipientName)) {
                        $recipientName = $user->name;
                    }
                    $userRole = strtolower($user->role ?: '');
                    if (in_array($userRole, ['driver', 'surveyor'])) {
                        $recipientRole = $userRole;
                    } else {
                        $recipientRole = 'team_member';
                    }
                }

                // 2. Check if recipient is linked to a Lead (as client, driver, or surveyor)
                $lead = Lead::where('email', $recipientEmail)->first();
                if ($lead) {
                    $leadId = $lead->id;
                    if (empty($recipientName)) {
                        $recipientName = $lead->name;
                    }
                    if ($recipientRole === 'recipient') {
                        $recipientRole = 'client';
                    }
                } else {
                    // Check if assigned as driver or surveyor on any lead
                    $driverLead = Lead::where('driver_email', $recipientEmail)->latest()->first();
                    if ($driverLead) {
                        $recipientRole = 'driver';
                        if (empty($recipientName)) {
                            $recipientName = $driverLead->driver_name ?: 'Driver';
                        }
                        $leadId = $driverLead->id;
                    } else {
                        $surveyorLead = Lead::where('surveyor_email', $recipientEmail)->latest()->first();
                        if ($surveyorLead) {
                            $recipientRole = 'surveyor';
                            if (empty($recipientName)) {
                                $recipientName = $surveyorLead->surveyor_name ?: 'Surveyor';
                            }
                            $leadId = $surveyorLead->id;
                        }
                    }
                }

                // 3. Fallback: extract lead ID from subject if present (e.g. #L-2601)
                if (!$leadId) {
                    if (preg_match('/#([A-Za-z0-9\-]+)/', $subject, $m)) {
                        $potentialLead = Lead::find($m[1]);
                        if ($potentialLead) {
                            $leadId = $potentialLead->id;
                            if ($recipientRole === 'recipient') {
                                if (strtolower($recipientEmail) === strtolower($potentialLead->email)) {
                                    $recipientRole = 'client';
                                    if (empty($recipientName)) {
                                        $recipientName = $potentialLead->name;
                                    }
                                }
                            }
                        }
                    }
                }

                // 4. Fallback to Contact if still recipient
                if ($recipientRole === 'recipient') {
                    $contact = Contact::where('email', $recipientEmail)->first();
                    if ($contact) {
                        $recipientRole = 'client';
                        if (empty($recipientName)) {
                            $recipientName = $contact->name;
                        }
                    }
                }

                // 5. Build secure tracking URL
                $baseUrl = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');
                $params = [
                    'recipient_email' => $recipientEmail,
                    'recipient_name'  => $recipientName ?: $recipientEmail,
                    'recipient_type'  => $recipientRole,
                    'email_type'      => $subject,
                    't'               => time(),
                ];
                if ($leadId) {
                    $params['lead_id'] = $leadId;
                }

                $trackingUrl = "{$baseUrl}/api/outlook/track?" . http_build_query($params);

                $pixelTag = '<img src="' . htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:none;width:1px;height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;" />';

                if (str_contains($htmlBody, '</body>')) {
                    $newHtml = str_replace('</body>', $pixelTag . '</body>', $htmlBody);
                } else {
                    $newHtml = $htmlBody . $pixelTag;
                }

                $email->html($newHtml);

                Log::info("Injected automatic email tracking pixel for {$recipientEmail} ({$recipientRole}) - Subject: {$subject}");
            } catch (\Throwable $e) {
                Log::warning("Failed to inject email tracking pixel: " . $e->getMessage());
            }
        });
    }
}
