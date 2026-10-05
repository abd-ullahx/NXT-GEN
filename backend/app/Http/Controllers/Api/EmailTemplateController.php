<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class EmailTemplateController extends Controller
{
    /**
     * Get the full catalog of Next Gen luxury HTML email templates.
     */
    public function index(): JsonResponse
    {
        $categories = [
            [
                'id'    => 'cat01_qualification',
                'name'  => 'Category 01 — Enquiry & Early Qualification',
                'count' => 6,
                'templates' => [
                    ['key' => 'cat01_qualification.01_welcome', 'title' => '01 | Private Client Welcome', 'subtitle' => 'A calmer move starts with clarity.'],
                    ['key' => 'cat01_qualification.02_initial_estimate', 'title' => '02 | Initial Indicative Quotation', 'subtitle' => 'An early view of your move investment.'],
                    ['key' => 'cat01_qualification.03_survey_suggested', 'title' => '03 | Survey Suggested', 'subtitle' => 'Precision before moving day.'],
                    ['key' => 'cat01_qualification.06_survey_required', 'title' => '06 | Survey Required (Action Required)', 'subtitle' => 'We need the full picture before we price.'],
                ],
            ],
            [
                'id'    => 'cat02_survey',
                'name'  => 'Category 02 — Survey Booking & Attendance',
                'count' => 8,
                'templates' => [
                    ['key' => 'cat02_survey.01_virtual_survey_booked', 'title' => '01 | Video / In-App Survey Booked', 'subtitle' => 'Your virtual survey is in the diary.'],
                    ['key' => 'cat02_survey.08_survey_completed', 'title' => '08 | Survey Completed', 'subtitle' => 'Thank you — we have what we need.'],
                ],
            ],
            [
                'id'    => 'cat03_quotation',
                'name'  => 'Category 03 — Quotation & Conversion',
                'count' => 13,
                'templates' => [
                    ['key' => 'cat03_quotation.01_bronze_proposal', 'title' => '01 | Bronze Signature Proposal', 'subtitle' => 'A considered move plan, now priced.'],
                    ['key' => 'cat03_quotation.13_booking_confirmation', 'title' => '13 | Booking Confirmed', 'subtitle' => 'Your move is officially in the diary.'],
                ],
            ],
            [
                'id'    => 'cat04_booking',
                'name'  => 'Category 04 — Booking & Pre-Dispatch',
                'count' => 3,
                'templates' => [
                    ['key' => 'cat04_booking.01_deposit_confirmed', 'title' => '01 | Deposit Confirmed', 'subtitle' => 'Deposit received — your date is secured.'],
                ],
            ],
            [
                'id'    => 'cat05_pre_move',
                'name'  => 'Category 05 — Pre-Move Preparation',
                'count' => 6,
                'templates' => [
                    ['key' => 'cat05_pre_move.01_prep_30d', 'title' => '01 | 30 Days to Go', 'subtitle' => 'The best moves feel organised before the crew arrives.'],
                    ['key' => 'cat05_pre_move.06_prep_1d', 'title' => '06 | Tomorrow (One Day Before)', 'subtitle' => 'Everything you need for the final evening.'],
                ],
            ],
            [
                'id'    => 'cat06_move_day',
                'name'  => 'Category 06 — Move Day Live Execution',
                'count' => 5,
                'templates' => [
                    ['key' => 'cat06_move_day.01_crew_en_route', 'title' => '01 | Crew En Route', 'subtitle' => 'Our vehicle & crew are on their way to you.'],
                ],
            ],
            [
                'id'    => 'cat07_aftercare',
                'name'  => 'Category 07 — Aftercare, Reviews & Referrals',
                'count' => 7,
                'templates' => [
                    ['key' => 'cat07_aftercare.01_move_complete_thank_you', 'title' => '01 | Move Complete Thank You', 'subtitle' => 'The move is finished. The care does not stop here.'],
                    ['key' => 'cat07_aftercare.06_referral_invitation', 'title' => '06 | Referral Invitation', 'subtitle' => 'Good moves are worth passing on.'],
                ],
            ],
        ];

        return response()->json(['categories' => $categories]);
    }

    /**
     * Preview a rendered HTML template for a specific lead.
     */
    public function preview(Request $request, string $leadId): JsonResponse
    {
        $lead = Lead::find($leadId);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $templateKey = $request->input('templateKey', 'cat01_qualification.01_welcome');
        $viewName = 'emails.templates.' . $templateKey;

        if (!View::exists($viewName)) {
            return response()->json(['error' => "Template view '{$viewName}' not found"], 404);
        }

        $html = View::make($viewName, [
            'lead'      => $lead,
            'publicUrl' => env('PUBLIC_BASE_URL', 'http://127.0.0.1:8000') . '/portal/' . $lead->id,
            'subject'   => 'Next Gen Relocation — Communication Preview',
        ])->render();

        return response()->json(['html' => $html, 'lead' => $lead]);
    }

    /**
     * Send selected template to a lead.
     */
    public function send(Request $request, string $leadId): JsonResponse
    {
        $lead = Lead::find($leadId);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $templateKey = $request->input('templateKey', 'cat01_qualification.01_welcome');
        $customSubject = $request->input('subject');
        $viewName = 'emails.templates.' . $templateKey;

        if (!View::exists($viewName)) {
            return response()->json(['error' => "Template view '{$viewName}' not found"], 404);
        }

        $subject = $customSubject ?: ('Next Gen Relocation — Update regarding ' . $lead->id);

        try {
            $renderedHtml = View::make($viewName, [
                'lead'      => $lead,
                'publicUrl' => env('PUBLIC_BASE_URL', 'http://127.0.0.1:8000') . '/portal/' . $lead->id,
                'subject'   => $subject,
            ])->render();

            Mail::queue($viewName, [
                'lead'      => $lead,
                'publicUrl' => env('PUBLIC_BASE_URL', 'http://127.0.0.1:8000') . '/portal/' . $lead->id,
                'subject'   => $subject,
            ], function ($message) use ($lead, $subject) {
                $message->to($lead->email, $lead->name)
                        ->subject($subject);
            });

            // Record sent email in `emails` table so staff can view sent emails in Email Inbox (/app/outlook)
            try {
                \App\Models\Email::create([
                    'message_id'   => 'MSG-' . \Illuminate\Support\Str::uuid(),
                    'direction'    => 'outbound',
                    'from_email'   => config('mail.from.address', 'abdullah18vk@gmail.com'),
                    'from_name'    => config('mail.from.name', 'Next Gen Relocation LTD'),
                    'to_email'     => $lead->email,
                    'subject'      => $subject,
                    'body_preview' => 'Template Email Sent: ' . $subject,
                    'body_html'    => $renderedHtml,
                    'is_read'      => true,
                    'received_at'  => now(),
                    'lead_id'      => $lead->id,
                ]);
            } catch (\Exception $ex) {
                \Illuminate\Support\Facades\Log::warning("Failed to record outbound email in DB: " . $ex->getMessage());
            }

            return response()->json([
                'message' => 'Email sent successfully!',
                'leadId'  => $lead->id,
                'recipient' => $lead->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }
}
