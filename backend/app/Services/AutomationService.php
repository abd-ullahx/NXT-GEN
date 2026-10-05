<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SurveyReminder;
use App\Models\Quotation;
use App\Models\QuotationReminder;
use App\Models\JobEvent;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * AutomationService — the single source of truth for the lead lifecycle.
 *
 * Flow (spec #2–#7):
 *   draft lead  ──approveLead()──►  welcome email + indicative draft quotation
 *   admin edits & sends quote ──sendQuotation()──►  quote email w/ approve link
 *                                                   + reminder cycle armed
 *   customer approves quote ──recordResponse('quotation')──►  survey-suggestion
 *                                                   email + reminder cycle re-armed
 *   customer books survey  ──recordResponse('survey')──►  shows in Survey section
 *
 * The reminder cycle (advanceReminders) only ever fires inside working hours,
 * spaces reminders by the configured interval, stops after max cycles, and is
 * cancelled the instant the customer responds (approve link or inbound reply).
 *
 * All the ad-hoc email code that used to live in OutlookService::syncEmails and
 * LeadController::store has been consolidated here.
 */
class AutomationService
{
    public function __construct()
    {
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Lifecycle transitions
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Admin approves a draft lead. Sends the welcome email and drafts an
     * indicative quotation that then appears in the Quotation section for the
     * admin to edit & send. Automation begins here (spec #3).
     */
    public function approveLead(Lead $lead): Lead
    {
        if ($lead->approved_at) {
            return $lead; // idempotent
        }

        $now = now();
        $lead->approved_at = $now;
        $lead->status      = 'new';
        $lead->stage       = 'Welcome Email';

        // Send the welcome email via Brevo SMTP
        $sent = $this->sendWelcomeEmail($lead);
        if ($sent) {
            $lead->welcome_email_sent_at = $now;
        }

        $lead->save();

        // Prepare the indicative quotation the admin will edit & send.
        $this->createIndicativeQuotation($lead);

        if (!$sent) {
            throw new \Exception("Failed to send the welcome email. Please verify your Brevo SMTP credentials in .env and retry.");
        }

        Log::info("Lead {$lead->id} approved — welcome sent, indicative quotation drafted.");

        return $lead->refresh();
    }

    /**
     * Build an indicative (draft) quotation for a lead if none exists yet.
     */
    public function createIndicativeQuotation(Lead $lead): Quotation
    {
        $existing = Quotation::where('lead_id', $lead->id)->first();
        if ($existing) {
            return $existing;
        }

        // Indicative pricing derived from the lead's estimated value or smart move type heuristics
        $base = (float) ($lead->est_value ?: 0);
        if ($base <= 0) {
            $mt = strtolower((string)($lead->move_type ?: ''));
            if (str_contains($mt, '1 bed') || str_contains($mt, 'studio') || str_contains($mt, 'flat')) {
                $base = 650.0;
            } elseif (str_contains($mt, '2 bed') || str_contains($mt, '3 bed') || str_contains($mt, 'residential')) {
                $base = 1200.0;
            } elseif (str_contains($mt, '4 bed') || str_contains($mt, '5 bed') || str_contains($mt, 'house')) {
                $base = 2200.0;
            } elseif (str_contains($mt, 'office') || str_contains($mt, 'commercial')) {
                $base = 3200.0;
            } else {
                $base = 1200.0;
            }
        }
        $packing   = round($base * 0.15, 2);
        $insurance = round($base * 0.05, 2);
        $subtotal  = round($base + $packing + $insurance, 2);
        $tax       = round($subtotal * 0.20, 2); // 20% VAT
        $total     = round($subtotal + $tax, 2);
        $deposit20 = round($total * 0.20, 2);

        $items = [
            ['label' => 'Removal service (' . ($lead->move_type ?: 'Move') . ')', 'qty' => 1, 'unit_price' => $base, 'amount' => $base],
            ['label' => 'Packing & materials', 'qty' => 1, 'unit_price' => $packing, 'amount' => $packing],
            ['label' => 'Transit insurance', 'qty' => 1, 'unit_price' => $insurance, 'amount' => $insurance],
        ];

        return Quotation::create([
            'id'                   => 'Q-' . strtoupper(substr(uniqid(), -6)),
            'lead_id'              => $lead->id,
            'quote_type'           => 'indicative',
            'quote_number'         => 'QT-' . str_pad((string) (Quotation::count() + 1001), 5, '0', STR_PAD_LEFT),
            'client_name'          => $lead->name,
            'client_email'         => $lead->email,
            'move_type'            => $lead->move_type,
            'from_location'        => $lead->from_location,
            'to_location'          => $lead->to_location,
            'move_date'            => $lead->move_date,
            'items'                => $items,
            'subtotal'             => $subtotal,
            'tax'                  => $tax,
            'total'                => $total,
            'deposit_percent'      => 20.00,
            'deposit_amount'       => $deposit20,
            'initial_deposit_paid' => false,
            'status'               => 'draft',
            'valid_until'          => now()->addDays(14)->format('Y-m-d'),
        ]);
    }

    /**
     * Create a post-survey quotation draft after the surveyor submits their
     * report. This is the final, accurate quote the admin edits and sends.
     * It lands as a draft — admin edits, then clicks Send.
     */
    public function createPostSurveyQuotation(Lead $lead): Quotation
    {
        $base = (float) ($lead->est_value ?: 0);
        if ($base <= 0) {
            $base = 1500.0;
        }

        // Post-survey quote is slightly more accurate — no packing estimate,
        // but a surveyor-inspection fee and a final removal fee.
        $surveyFee = round($base * 0.05, 2);     // 5% surveyor admin fee
        $packing   = round($base * 0.12, 2);     // 12% packing
        $insurance = round($base * 0.05, 2);     // 5% transit insurance
        $subtotal  = round($base + $surveyFee + $packing + $insurance, 2);
        $tax       = round($subtotal * 0.20, 2); // 20% VAT
        $total     = round($subtotal + $tax, 2);

        $items = [
            ['label' => 'Removal service — post-survey (' . ($lead->move_type ?: 'Move') . ')', 'qty' => 1, 'unit_price' => $base,      'amount' => $base],
            ['label' => 'Surveyor inspection fee',                                                'qty' => 1, 'unit_price' => $surveyFee, 'amount' => $surveyFee],
            ['label' => 'Packing & protective materials',                                         'qty' => 1, 'unit_price' => $packing,   'amount' => $packing],
            ['label' => 'Full-value transit insurance',                                           'qty' => 1, 'unit_price' => $insurance, 'amount' => $insurance],
        ];

        // Format detailed notes including client and survey report details
        $notes = "Post-survey final quotation.\n\n";
        $notes .= "--- CLIENT DETAILS ---\n";
        $notes .= "Client Name: " . ($lead->name ?: 'N/A') . "\n";
        $notes .= "Client Email: " . ($lead->email ?: 'N/A') . "\n";
        $notes .= "Client Phone: " . ($lead->phone ?: 'N/A') . "\n";
        $notes .= "Move Type: " . ($lead->move_type ?: 'N/A') . "\n";
        $notes .= "Collection: " . ($lead->from_location ?: 'N/A') . "\n";
        $notes .= "Destination: " . ($lead->to_location ?: 'N/A') . "\n";
        $notes .= "Move Date: " . ($lead->move_date ? \Carbon\Carbon::parse($lead->move_date)->format('Y-m-d') : 'TBD') . "\n\n";
        
        $notes .= "--- SURVEY ASSESSMENT REPORT ---\n";
        $notes .= "Surveyor: " . ($lead->surveyor_name ?: 'Assigned Surveyor') . "\n";
        $notes .= "Completion Date: " . ($lead->surveyor_completed_at ? \Carbon\Carbon::parse($lead->surveyor_completed_at)->format('Y-m-d H:i') : now()->format('Y-m-d H:i')) . "\n";
        $notes .= "Report Notes:\n" . ($lead->surveyor_report_notes ?: 'No report notes provided.');

        // Build 3 Tiered Packages (Basic, Standard, Premium)
        $basicSubtotal    = round($base * 0.85, 2);
        $basicTax         = round($basicSubtotal * 0.20, 2);
        $basicTotal       = round($basicSubtotal + $basicTax, 2);

        $standardSubtotal = round($base * 1.15, 2);
        $standardTax      = round($standardSubtotal * 0.20, 2);
        $standardTotal    = round($standardSubtotal + $standardTax, 2);

        $premiumSubtotal  = round($base * 1.50, 2);
        $premiumTax       = round($premiumSubtotal * 0.20, 2);
        $premiumTotal     = round($premiumSubtotal + $premiumTax, 2);

        $packages = [
            [
                'name'        => 'Basic',
                'tagline'     => 'Self-Packing & Standard Removal',
                'subtotal'    => $basicSubtotal,
                'tax'         => $basicTax,
                'total'       => $basicTotal,
                'deposit_20'  => round($basicTotal * 0.20, 2),
                'features'    => [
                    'Professional Removal Van & Driver',
                    'Standard Loading & Transport',
                    'Basic Goods-in-Transit Insurance',
                    'Self-Packing by Customer',
                ],
            ],
            [
                'name'        => 'Standard',
                'tagline'     => 'Full Service & Dismantling (Recommended)',
                'subtotal'    => $standardSubtotal,
                'tax'         => $standardTax,
                'total'       => $standardTotal,
                'deposit_20'  => round($standardTotal * 0.20, 2),
                'features'    => [
                    'Professional Removal Van & Full Crew',
                    'Full Furniture Dismantling & Reassembly',
                    'Loading, Transport & Unloading',
                    'Protective Blankets & Mattress Covers',
                    'Standard Transit & Public Liability Insurance',
                ],
            ],
            [
                'name'        => 'Premium',
                'tagline'     => 'White-Glove VIP Full Relocation',
                'subtotal'    => $premiumSubtotal,
                'tax'         => $premiumTax,
                'total'       => $premiumTotal,
                'deposit_20'  => round($premiumTotal * 0.20, 2),
                'features'    => [
                    'Dedicated VIP Removal Crew & Luton Vans',
                    'Full Professional Packing & Unpacking Service',
                    'Complete Furniture Dismantling & Reassembly',
                    'Fragile & Fine Art Specialized Bubble Wrapping',
                    'Full-Value Comprehensive Transit Insurance',
                    'Priority Time Window & Dedicated Move Manager',
                ],
            ],
        ];

        $quote = Quotation::create([
            'id'                   => 'Q-' . strtoupper(substr(uniqid(), -6)),
            'lead_id'              => $lead->id,
            'quote_type'           => 'post-survey',
            'quote_number'         => 'QT-' . str_pad((string) (Quotation::count() + 1001), 5, '0', STR_PAD_LEFT),
            'client_name'          => $lead->name,
            'client_email'         => $lead->email,
            'move_type'            => $lead->move_type,
            'from_location'        => $lead->from_location,
            'to_location'          => $lead->to_location,
            'move_date'            => $lead->move_date,
            'items'                => $items,
            'packages'             => $packages,
            'subtotal'             => $standardSubtotal,
            'tax'                  => $standardTax,
            'total'                => $standardTotal,
            'deposit_percent'      => 20.00,
            'deposit_amount'       => round($standardTotal * 0.20, 2),
            'initial_deposit_paid' => (bool)$lead->initial_deposit_paid,
            'notes'                => $notes,
            'status'               => 'draft',
            'valid_until'   => now()->addDays(14)->format('Y-m-d'),
        ]);

        Log::info("Lead {$lead->id}: post-survey quotation {$quote->id} ({$quote->quote_number}) drafted.");

        return $quote;
    }

    /**
     * Admin sends the (edited) quotation to the customer with an approve link.
     * Arms the quotation reminder cycle (spec #4/#5).
     */
    public function sendQuotation(Quotation $quote): Quotation
    {
        $lead = $quote->lead_id ? Lead::find($quote->lead_id) : null;
        $now  = now();

        $sent = $this->sendQuotationEmail($quote, $lead);

        $quote->status  = 'sent';
        $quote->sent_at = $now;
        $quote->save();

        if ($lead) {
            $lead->update([
                'quotation_sent_at' => $now,
                'quotation_status'  => 'sent',
                'status'            => 'quoted',
                'stage'             => 'Quotation',
                'last_response_at'  => null,
            ]);
            // Schedule the 5 quotation reminders (default intervals: 2h, 4h, 7h, 24h, 48h)
            $this->scheduleQuotationReminders($quote);
        }

        Log::info("Quotation {$quote->id} sent to {$quote->client_email} (email sent: " . ($sent ? 'yes' : 'no') . ").");

        return $quote->refresh();
    }

    /**
     * Record that the customer responded (approve link clicked or inbound
     * reply). Cancels pending reminders and advances the funnel (spec #5/#6/#7).
     *
     * @param string $type 'quotation' | 'survey'
     */
    public function recordResponse(Lead $lead, string $type): void
    {
        $now = now();
        $lead->last_response_at = $now;

        // Cancel the pending reminder cycle.
        $lead->reminder_next_at = null;
        $lead->reminder_context = null;
        $this->cancelReminderEvents($lead);

        if ($type === 'quotation') {
            $lead->quotation_status      = 'approved';
            $lead->quotation_approved_at = $now;

            // Stop/cancel pending quotation reminders
            QuotationReminder::where('lead_id', $lead->id)
                ->where('status', 'pending')
                ->update(['status' => 'stopped']);

            // Find the quotation that was approved
            $quote = Quotation::where('lead_id', $lead->id)
                ->whereIn('status', ['sent', 'draft'])
                ->orderByDesc('sent_at')
                ->first();
            if ($quote) {
                $quote->update(['status' => 'approved', 'approved_at' => $now]);
                try {
                    $this->sendAdminQuotationApprovedEmail($quote, $lead);
                } catch (\Throwable $e) {
                    Log::warning("Could not send admin quotation approval notification: " . $e->getMessage());
                }
            }

            $isPostSurvey = $quote && $quote->quote_type === 'post-survey';

            if ($isPostSurvey) {
                // Post-survey final quotation approved -> Awaiting 20% Deposit payment
                $lead->status = 'quote-approved';
                $lead->stage  = 'Quotation Approved';
                $lead->save();

                Log::info("Lead {$lead->id} post-survey final quotation approved — awaiting deposit payment.");
            } else {
                // Indicative quote approved -> Move to survey phase
                $lead->status = 'quote-approved';
                $lead->stage  = 'Quotation Approved';
                $lead->save();

                // Spec #6: instantly send the survey-suggestion email and arm the survey reminder cycle.
                if ($this->sendSurveySuggestionEmail($lead)) {
                    $lead->survey_email_sent_at = $now;
                }
                $lead->stage = 'Survey';
                $lead->save();
                $this->armReminders($lead, 'survey');

                Log::info("Lead {$lead->id} indicative quotation approved — survey suggestion sent.");
            }
        } elseif ($type === 'survey') {
            // Survey-specific fields are set by the caller (survey date/type).
            $lead->status = 'survey-booked';
            $lead->stage  = 'Survey';
            $lead->save();
            Log::info("Lead {$lead->id} survey scheduled by customer.");
        } else {
            $lead->save();
        }
    }

    /**
     * Admin approves a scheduled survey (spec #7).
     * Also schedules the 4 pre-survey reminder emails.
     */
    public function approveSurvey(Lead $lead): Lead
    {
        // 1. Automatically generate/ensure User account for mobile app login
        $plainPassword = $lead->app_user_password;
        if (!$plainPassword) {
            $plainPassword = 'NG-' . rand(100000, 999999);
        }

        if ($lead->email) {
            $user = \App\Models\User::where('email', strtolower(trim($lead->email)))->first();
            if (!$user) {
                \App\Models\User::create([
                    'name'        => $lead->name ?: 'Customer',
                    'email'       => strtolower(trim($lead->email)),
                    'password'    => \Illuminate\Support\Facades\Hash::make($plainPassword),
                    'role'        => 'staff',
                    'permissions' => [
                        'leads' => true,
                        'calendar' => true,
                        'contacts' => true,
                    ],
                ]);
            }
        }

        $lead->update([
            'survey_status'      => 'approved',
            'survey_approved_at' => now(),
            'status'             => 'survey-approved',
            'stage'              => 'Survey Approved',
            'app_user_password'  => $plainPassword,
        ]);
        $this->cancelReminderEvents($lead);

        // Schedule the 4 countdown reminder emails for the survey day.
        $this->scheduleSurveyReminders($lead->fresh());

        // Send official Survey Approved email to client
        $this->sendSurveyApprovedEmail($lead->fresh());

        return $lead->refresh();
    }

    /**
     * Admin proposes a new survey schedule (date & exact time).
     */
    public function proposeSurveyReschedule(Lead $lead, string $newDate, string $newTime): Lead
    {
        $token = 'RSC-' . bin2hex(random_bytes(16));
        $lead->update([
            'reschedule_proposed_date' => $newDate,
            'reschedule_proposed_time' => $newTime,
            'reschedule_status'        => 'proposed',
            'reschedule_token'         => $token,
        ]);

        $this->sendSurveyRescheduleProposedEmail($lead->fresh());
        Log::info("Lead {$lead->id}: Reschedule proposed to {$newDate} at {$newTime}. Token: {$token}");

        return $lead->refresh();
    }

    /**
     * Client clicks email link to confirm/approve proposed survey schedule.
     */
    public function confirmClientReschedule(string $token): ?Lead
    {
        $lead = Lead::where('reschedule_token', $token)->first();
        if (!$lead) {
            return null;
        }

        $newDate = $lead->reschedule_proposed_date ?: $lead->survey_requested_date;
        $newTime = $lead->reschedule_proposed_time ?: $lead->survey_requested_time_range;

        $lead->update([
            'survey_requested_date'       => $newDate,
            'survey_requested_time_range' => $newTime,
            'reschedule_status'           => 'client_approved',
            'reschedule_token'            => null,
        ]);

        // Update linked JobEvent calendar entry
        try {
            JobEvent::where('lead_id', $lead->id)
                ->where('type', 'Survey')
                ->update([
                    'event_date' => $newDate,
                    'event_time' => $newTime,
                ]);
        } catch (\Throwable $e) {
            Log::warning("confirmClientReschedule: failed to update JobEvent calendar: " . $e->getMessage());
        }

        // Notify admin that client accepted proposed time
        $this->sendAdminRescheduleAcceptedEmail($lead->fresh());
        Log::info("Lead {$lead->id}: Client confirmed proposed schedule ({$newDate} at {$newTime}).");

        return $lead->refresh();
    }

    /**
     * Schedule (or re-schedule) the 4 timed pre-survey reminder emails.
     * Called whenever survey is approved or survey datetime changes.
     */
    public function scheduleSurveyReminders(Lead $lead): void
    {
        $dateStr = $lead->survey_requested_date;  // e.g. "2026-08-10"
        $timeStr = $lead->survey_requested_time_range ?? '09:00 AM';

        // Delete any existing reminders so we can recreate cleanly.
        SurveyReminder::where('lead_id', $lead->id)->delete();

        if (!$dateStr) {
            Log::info("Lead {$lead->id}: no survey_requested_date — reminders not scheduled.");
            return;
        }

        // Parse the survey datetime in the business timezone.
        $tz = config('automation.business_timezone', 'Europe/London');
        try {
            // Handle time formats like "10:00 AM", "14:30", "10:00-12:00 AM" etc.
            $cleanTime = preg_replace('/[–\-].+/', '', $timeStr); // strip range e.g. "10:00 AM - 12:00 PM"
            $cleanTime = trim($cleanTime);
            $surveyAt  = \Carbon\Carbon::createFromFormat(
                str_contains($cleanTime, 'M') ? 'Y-m-d h:i A' : 'Y-m-d H:i',
                $dateStr . ' ' . $cleanTime,
                $tz
            )->setTimezone('UTC');
        } catch (\Throwable $e) {
            // Fallback: 9 AM on the survey day.
            $surveyAt = \Carbon\Carbon::parse($dateStr . ' 09:00:00', $tz)->setTimezone('UTC');
            Log::warning("Lead {$lead->id}: could not parse survey time '{$timeStr}', defaulting to 09:00.");
        }

        $reminders = [
            ['type' => '1_day',   'label' => '1 Day Before',   'offset' => 60 * 60 * 24],
            ['type' => '6_hours', 'label' => '6 Hours Before', 'offset' => 60 * 60 * 6],
            ['type' => '1_hour',  'label' => '1 Hour Before',  'offset' => 60 * 60],
            ['type' => '15_min',  'label' => '15 Minutes Before', 'offset' => 60 * 15],
        ];

        foreach ($reminders as $r) {
            $scheduledAt = $surveyAt->copy()->subSeconds($r['offset']);
            SurveyReminder::create([
                'lead_id'      => $lead->id,
                'type'         => $r['type'],
                'label'        => $r['label'],
                'scheduled_at' => $scheduledAt,
                'status'       => $scheduledAt->isPast() ? 'skipped' : 'pending',
            ]);
        }

        Log::info("Lead {$lead->id}: 4 survey day reminders scheduled (survey at {$surveyAt->toIso8601String()}).");
    }

    /**
     * Fire any due survey-day reminder emails.
     * Called by the automation:run-reminders command every minute.
     * Returns the count of emails actually sent.
     */
    public function fireDueSurveyReminders(): int
    {
        $due = SurveyReminder::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->with('lead')
            ->orderBy('scheduled_at')
            ->limit(5) // Max 5 per sweep; rate-limiting is less critical with Brevo SMTP but retained as a precaution - can be relaxed later
            ->get();

        $sent = 0;
        foreach ($due as $index => $reminder) {
            if ($index > 0) {
                // 200ms throttle (non-blocking) — sufficient for Brevo SMTP rate limits
                usleep(200000);
            }
            $lead = $reminder->lead;
            if (!$lead) {
                $reminder->update(['status' => 'stopped']);
                continue;
            }

            $ok = $this->sendSurveyDayReminder($lead, $reminder);
            $reminder->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
            if ($ok) {
                $sent++;
            }
            Log::info("Survey reminder [{$reminder->type}] fired for lead {$lead->id} (sent: " . ($ok ? 'yes' : 'no') . ").");
        }

        return $sent;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Reminder engine
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Arm (or re-arm) the reminder cycle for a lead in a given context.
     */
    public function armReminders(Lead $lead, string $context): void
    {
        $next = $this->nextWorkingSlot(now());
        $lead->update([
            'reminder_context' => $context,
            'reminder_stage'   => 0,
            'reminder_next_at' => $next,
            'last_response_at' => null,
        ]);
        $this->upsertReminderEvent($lead, $next);
    }

    /**
     * Send any reminders that are due. Called every few minutes by the
     * automation:run-reminders command. Respects working hours, spacing, and
     * the max-cycle cap; stops the moment a customer responds (spec #5).
     */
    public function advanceReminders(): int
    {
        $max = (int) config('automation.reminder_max_cycles', 4);
        $sent = 0;

        $due = Lead::whereNotNull('reminder_next_at')
            ->whereNotNull('reminder_context')
            ->whereNull('last_response_at')
            ->where('reminder_next_at', '<=', now())
            ->orderBy('reminder_next_at')
            ->limit(5) // Max 5 per sweep; rate-limiting is less critical with Brevo SMTP but retained as a precaution - can be relaxed later
            ->get();

        foreach ($due as $index => $lead) {
            if ($index > 0) {
                // 200ms throttle (non-blocking) — sufficient for Brevo SMTP rate limits
                usleep(200000);
            }
            // Never fire outside working hours — push to the next valid slot.
            if (!$this->isWithinWorkingHours(now())) {
                $next = $this->nextWorkingSlot(now());
                $lead->update(['reminder_next_at' => $next]);
                $this->upsertReminderEvent($lead, $next);
                continue;
            }

            // Cap reached — give up gracefully.
            if ($lead->reminder_stage >= $max) {
                $lead->update(['reminder_next_at' => null]);
                $this->cancelReminderEvents($lead);
                Log::info("Lead {$lead->id} reminder cycle exhausted after {$max} attempts.");
                continue;
            }

            $ok = $lead->reminder_context === 'quotation'
                ? $this->sendQuotationReminder($lead)
                : $this->sendSurveyReminder($lead);

            $stage = $lead->reminder_stage + 1;
            $lead->reminder_stage = $stage;

            if ($stage >= $max) {
                // That was the last reminder.
                $lead->reminder_next_at = null;
                $this->cancelReminderEvents($lead);
            } else {
                $next = $this->nextWorkingSlot(now());
                $lead->reminder_next_at = $next;
                $this->upsertReminderEvent($lead, $next);
            }
            $lead->save();

            if ($ok) {
                $sent++;
            }
            Log::info("Sent {$lead->reminder_context} reminder #{$stage} to lead {$lead->id}.");
        }

        return $sent;
    }

    /**
     * Compute the next reminder slot: adds the configured interval (+jitter)
     * to $from, then clamps into the working-hours window in the business
     * timezone. Returned in the app timezone for safe storage/comparison.
     */
    public function nextWorkingSlot(?Carbon $from = null): Carbon
    {
        $tz       = config('automation.business_timezone', 'Europe/London');
        $start    = (int) config('automation.work_start_hour', 9);
        $end      = (int) config('automation.work_end_hour', 18);
        $interval = (int) config('automation.reminder_interval_minutes', 120);
        $jitter   = (int) config('automation.reminder_jitter_minutes', 60);

        $from = ($from ? $from->copy() : now())->setTimezone($tz);
        $extra = $interval + ($jitter > 0 ? random_int(0, $jitter) : 0);
        $slot = $from->addMinutes($extra);

        return $this->clampToWorkingHours($slot, $start, $end)
            ->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Is the given moment inside the business working-hours window?
     */
    public function isWithinWorkingHours(?Carbon $when = null): bool
    {
        $tz    = config('automation.business_timezone', 'Europe/London');
        $start = (int) config('automation.work_start_hour', 9);
        $end   = (int) config('automation.work_end_hour', 18);

        $when = ($when ? $when->copy() : now())->setTimezone($tz);
        if ($when->isWeekend()) {
            return false;
        }

        return $when->hour >= $start && $when->hour < $end;
    }

    /**
     * Push a slot into the next valid working moment (business tz).
     */
    private function clampToWorkingHours(Carbon $slot, int $start, int $end): Carbon
    {
        if ($slot->hour < $start) {
            $slot->setTime($start, 0);
        }
        if ($slot->hour >= $end) {
            $slot->addDay()->setTime($start, (int) $slot->minute % 30);
        }
        while ($slot->isWeekend()) {
            $slot->addDay()->setTime($start, 0);
        }

        return $slot;
    }

    // --- Reminder calendar markers -------------------------------------------

    /**
     * Keep a single calendar marker in sync with the lead's next reminder so
     * reminders are visible/editable on the calendar (spec #8).
     */
    private function upsertReminderEvent(Lead $lead, Carbon $when): void
    {
        try {
            $local = $when->copy()->setTimezone(config('automation.business_timezone', 'Europe/London'));
            JobEvent::updateOrCreate(
                ['lead_id' => $lead->id, 'type' => 'Reminder'],
                [
                    'id'         => 'REM-' . $lead->id,
                    'title'      => "Follow-up ({$lead->reminder_context}) - {$lead->name}",
                    'status'     => 'booked',
                    'event_date' => $local->format('Y-m-d'),
                    'event_time' => $local->format('H:i'),
                    'driver'     => 'Automation',
                    'vehicle'    => 'N/A',
                    'location'   => 'N/A',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("AutomationService: upsertReminderEvent failed for lead {$lead->id}: " . $e->getMessage());
        }
    }

    /**
     * Delete any calendar Reminder marker for the given lead.
     */
    private function cancelReminderEvents(Lead $lead): void
    {
        try {
            JobEvent::where('lead_id', $lead->id)->where('type', 'Reminder')->delete();
        } catch (\Throwable $e) {
            Log::warning("AutomationService: cancelReminderEvents failed for lead {$lead->id}: " . $e->getMessage());
        }
    }

    // --- Email builders -------------------------------------------------------

    private function sendWelcomeEmail(Lead $lead): bool
    {
        if (!$lead->email) {
            return false;
        }
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Welcome Email"));
        return $this->trySendMail(
            $lead->email,
            new \App\Mail\WelcomeEmail($lead, $trackingUrl)
        );
    }

    private function sendQuotationEmail(Quotation $quote, ?Lead $lead): bool
    {
        $email = $quote->client_email;
        if (!$email) {
            return false;
        }
        $targetLink  = $this->publicUrl("/quotation/approve?quote_id={$quote->id}");
        $emailType   = $quote->quote_type === 'post-survey' ? 'Post-Survey Final Quotation' : 'Initial Quotation';
        $approveLink = $lead ? $this->publicUrl("/api/outlook/click?lead_id={$lead->id}&label=" . urlencode("Approve {$emailType}") . "&target=" . urlencode($targetLink)) : $targetLink;
        $trackingUrl = $lead ? $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode($emailType) . "&quote_number=" . urlencode($quote->quote_number ?: $quote->id)) : '';

        return $this->trySendMail(
            $email,
            new \App\Mail\QuotationEmail($quote, $lead, $approveLink, $trackingUrl)
        );
    }

    private function sendSurveySuggestionEmail(Lead $lead): bool
    {
        if (!$lead->email) {
            return false;
        }
        $targetLink  = $this->publicUrl("/book-survey?lead_id={$lead->id}");
        $bookLink    = $this->publicUrl("/api/outlook/click?lead_id={$lead->id}&label=" . urlencode("Book Pre-Move Survey") . "&target=" . urlencode($targetLink));
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Survey Booking Email"));

        return $this->trySendMail(
            $lead->email,
            new \App\Mail\SurveySuggestionEmail($lead, $bookLink, $trackingUrl)
        );
    }

    private function sendQuotationReminder(Lead $lead): bool
    {
        if (!$lead->email) {
            return false;
        }
        $quote = Quotation::where('lead_id', $lead->id)->orderByDesc('sent_at')->first();
        $targetLink  = $quote
            ? $this->publicUrl("/quotation/approve?quote_id={$quote->id}")
            : $this->publicUrl("/book-survey?lead_id={$lead->id}");
        $approveLink = $this->publicUrl("/api/outlook/click?lead_id={$lead->id}&label=" . urlencode("Quotation Reminder Link") . "&target=" . urlencode($targetLink));
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Quotation Reminder"));

        return $this->trySendMail(
            $lead->email,
            new \App\Mail\QuotationReminderEmail($lead, $approveLink, $trackingUrl)
        );
    }

    private function sendSurveyReminder(Lead $lead): bool
    {
        if (!$lead->email) {
            return false;
        }
        $targetLink  = $this->publicUrl("/book-survey?lead_id={$lead->id}");
        $bookLink    = $this->publicUrl("/api/outlook/click?lead_id={$lead->id}&label=" . urlencode("Survey Reminder Link") . "&target=" . urlencode($targetLink));
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Survey Reminder"));

        return $this->trySendMail(
            $lead->email,
            new \App\Mail\SurveyReminderEmail($lead, $bookLink, $trackingUrl)
        );
    }

    /**
     * Send a timed survey-day countdown reminder email to the customer.
     */
    private function sendSurveyDayReminder(Lead $lead, SurveyReminder $reminder): bool
    {
        if (!$lead->email) {
            return false;
        }
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Survey Day Reminder (" . $reminder->label . ")"));

        return $this->trySendMail(
            $lead->email,
            new \App\Mail\SurveyDayReminderEmail($lead, $reminder, $trackingUrl)
        );
    }

    // --- Helpers --------------------------------------------------------------

    /** Build a public URL from the configured base. */
    private function publicUrl(string $path): string
    {
        return rtrim(config('automation.public_base_url', config('app.url')), '/') . $path;
    }

    /**
     * Attempt to send a Mailable via Laravel's native Mail system (Brevo SMTP).
     * Emails are dispatched AFTER the HTTP response is returned to the client
     * using app()->terminating() so that SMTP latency never blocks page loads.
     */
    private function trySendMail(string $to, \Illuminate\Mail\Mailable $mailable): bool
    {
        try {
            $fromAddress = config('mail.from.address', 'info@nextgenrelocation.co.uk');
            $fromName    = config('mail.from.name', 'Next Gen Relocation');
            $subject     = method_exists($mailable, 'envelope') ? ($mailable->envelope()->subject ?? 'CRM Outbound Email') : 'CRM Outbound Email';
            $leadId      = property_exists($mailable, 'lead') && $mailable->lead ? $mailable->lead->id : Lead::where('email', $to)->value('id');

            // Pre-render the email body now (while models are still in scope)
            $htmlContent = '';
            $bodyPreview = '';
            try {
                $htmlContent = $mailable->render();
                $bodyPreview = strip_tags(substr($htmlContent, 0, 250));
            } catch (\Throwable $rendEx) {
                $bodyPreview = "Outbound email sent via Brevo SMTP [{$fromAddress}]";
                $htmlContent = "<p>Outbound email sent via Brevo SMTP to {$to}.</p>";
            }

            // Record outbound email in DB immediately (fast DB write)
            try {
                Email::create([
                    'message_id'   => 'brevo-' . uniqid(),
                    'direction'    => 'outbound',
                    'from_email'   => $fromAddress,
                    'from_name'    => $fromName,
                    'to_email'     => $to,
                    'subject'      => $subject,
                    'body_preview' => $bodyPreview,
                    'body_html'    => $htmlContent,
                    'is_read'      => true,
                    'received_at'  => now(),
                    'lead_id'      => $leadId,
                ]);
            } catch (\Throwable $ex) {
                Log::warning("AutomationService: failed to record outbound email in DB: " . $ex->getMessage());
            }

            // Dispatch SMTP send to the database queue instead of blocking the PHP dev server.
            try {
                \Illuminate\Support\Facades\Mail::to($to)->queue($mailable);
                Log::info("AutomationService: email queued for {$to}.");
            } catch (\Throwable $e) {
                Log::warning("AutomationService: failed to queue email to {$to}: " . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("AutomationService: failed to prepare email to {$to}: " . $e->getMessage());
            return false;
        }
    }

    public function sendSurveyApprovedEmail(Lead $lead): bool
    {
        if (!$lead->email) {
            return false;
        }
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Survey Approved Email"));
        return $this->trySendMail(
            $lead->email,
            new \App\Mail\SurveyApprovedEmail($lead, $trackingUrl)
        );
    }

    public function sendSurveyRescheduleProposedEmail(Lead $lead): bool
    {
        if (!$lead->email || !$lead->reschedule_token) {
            return false;
        }
        $targetLink  = $this->publicUrl("/api/survey/approve-reschedule?token={$lead->reschedule_token}");
        $approveLink = $this->publicUrl("/api/outlook/click?lead_id={$lead->id}&label=" . urlencode("Approve Survey Reschedule") . "&target=" . urlencode($targetLink));
        $trackingUrl = $this->publicUrl("/api/outlook/track?lead_id={$lead->id}&email_type=" . urlencode("Survey Reschedule Proposal"));
        return $this->trySendMail(
            $lead->email,
            new \App\Mail\SurveyRescheduleProposedEmail($lead, $approveLink, $trackingUrl)
        );
    }

    public function sendAdminRescheduleAcceptedEmail(Lead $lead): bool
    {
        $adminEmail = config('mail.from.address', 'abdullah18vk@gmail.com');
        return $this->trySendMail(
            $adminEmail,
            new \App\Mail\AdminRescheduleAcceptedEmail($lead)
        );
    }

    public function sendAdminQuotationApprovedEmail(Quotation $quote, Lead $lead): bool
    {
        $adminEmail = config('mail.from.address', 'abdullah18vk@gmail.com');
        return $this->trySendMail(
            $adminEmail,
            new \App\Mail\AdminQuotationApprovedEmail($quote, $lead)
        );
    }

    /**
     * Schedule the 5 follow-up quotation reminders (2h, 4h, 7h, Next Day, 2 Days)
     */
    public function scheduleQuotationReminders(Quotation $quote): void
    {
        // Delete any existing quotation reminders first to avoid duplicates
        QuotationReminder::where('quotation_id', $quote->id)->delete();

        $start = 9;
        $end = 18;

        $r1 = $this->clampToWorkingHours(now()->addHours(2), $start, $end);
        $r2 = $this->clampToWorkingHours(now()->addHours(4), $start, $end);
        $r3 = $this->clampToWorkingHours(now()->addHours(7), $start, $end);
        $r4 = $this->clampToWorkingHours(now()->addDay()->setTime(9, 0), $start, $end);
        $r5 = $this->clampToWorkingHours(now()->addDays(2)->setTime(9, 0), $start, $end);

        $reminders = [
            ['label' => 'Reminder 1 (2h)', 'time' => $r1],
            ['label' => 'Reminder 2 (4h)', 'time' => $r2],
            ['label' => 'Reminder 3 (7h)', 'time' => $r3],
            ['label' => 'Reminder 4 (Next Day)', 'time' => $r4],
            ['label' => 'Reminder 5 (2 Days)', 'time' => $r5],
        ];

        foreach ($reminders as $r) {
            QuotationReminder::create([
                'lead_id'      => $quote->lead_id,
                'quotation_id' => $quote->id,
                'label'        => $r['label'],
                'scheduled_at' => $r['time'],
                'status'       => 'pending',
            ]);
        }

        Log::info("Quotation {$quote->id}: 5 quotation reminders scheduled.");
    }

    /**
     * Send any due quotation reminders.
     */
    public function fireDueQuotationReminders(): int
    {
        $due = QuotationReminder::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->with('lead')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $sent = 0;
        foreach ($due as $index => $reminder) {
            if ($index > 0) {
                usleep(200000); // 200ms non-blocking throttle
            }
            $lead = $reminder->lead;
            if (!$lead) {
                $reminder->update(['status' => 'stopped']);
                continue;
            }

            // Fire quotation reminder email
            $ok = $this->sendQuotationReminder($lead);
            $reminder->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
            if ($ok) {
                $sent++;
            }
            Log::info("Quotation reminder [{$reminder->id}] fired for lead {$lead->id} (sent: " . ($ok ? 'yes' : 'no') . ").");
        }

        return $sent;
    }
}