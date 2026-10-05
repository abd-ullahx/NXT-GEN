<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\CallSession;
use App\Models\SurveyReminder;
use App\Models\QuotationReminder;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function __construct(private AutomationService $automation)
    {
    }

    private function syncCallSession(Lead $lead, Request $request, string $status, ?string $endReason = null): void
    {
        $session = CallSession::where('lead_id', (string) $lead->id)
            ->where('room_id', $lead->video_call_room_id)
            ->latest('id')
            ->first();

        if (!$session) {
            $callerId = (string) ($request->input('caller_id') ?: ($request->input('callerId') ?: ($request->user()?->id ?: 'mobile')));
            $isVideo = $request->has('is_video')
                ? $request->boolean('is_video')
                : ($request->has('isVideo') ? $request->boolean('isVideo') : (bool) $lead->video_call_is_video);

            $session = CallSession::create([
                'lead_id' => (string) $lead->id,
                'caller_id' => $callerId,
                'caller_role' => $request->input('caller_role') ?: ($request->input('callerRole') ?: ($lead->video_call_caller_role ?: 'surveyor')),
                'target_role' => $request->input('target_role') ?: ($request->input('targetRole') ?: 'admin'),
                'target_id' => $request->input('target_user_id') ?: ($request->input('targetId') ?: null),
                'room_id' => $lead->video_call_room_id ?: ('call_' . $lead->id),
                'is_video' => $isVideo,
                'call_type' => $isVideo ? 'video' : 'audio',
                'status' => $status,
            ]);
        } else {
            $session->status = $status;
        }

        if ($status === 'in_progress') {
            $session->accepted_by = (string) ($request->user()?->id ?: ($request->input('accepted_by') ?: 'admin'));
            $session->started_at = $session->started_at ?: now();
        }

        if (in_array($status, ['declined', 'missed', 'completed', 'ended', 'failed'], true)) {
            $session->ended_at = now();
            $session->end_reason = $endReason ?: $status;
            $session->duration_seconds = $session->started_at ? max(0, $session->started_at->diffInSeconds(now())) : 0;
        }

        $session->save();
    }

    private function formatIso(mixed $val): ?string
    {
        if (!$val) return null;
        if ($val instanceof \Carbon\CarbonInterface || $val instanceof \DateTimeInterface) {
            return $val->toIso8601String();
        }
        try {
            return \Carbon\Carbon::parse($val)->toIso8601String();
        } catch (\Throwable $e) {
            return (string) $val;
        }
    }

    /**
     * Serialise a lead to the camelCase shape the panel expects.
     */
    private function present(Lead $l): array
    {
        return [
            'id'                   => $l->id,
            'name'                 => $l->name,
            'email'                => $l->email,
            'phone'                => $l->phone,
            'source'               => $l->source,
            'status'               => $l->status,
            'stage'                => $l->stage ?: 'New',
            'approvedAt'           => $this->formatIso($l->approved_at),
            'welcomeEmailSentAt'   => $this->formatIso($l->welcome_email_sent_at),
            'surveyEmailSentAt'    => $this->formatIso($l->survey_email_sent_at),
            'surveyRequestedAt'    => $this->formatIso($l->survey_requested_at),
            'surveyBookedAt'       => $this->formatIso($l->survey_booked_at),
            'surveyApprovedAt'     => $this->formatIso($l->survey_approved_at),
            'appUserPassword'      => $l->app_user_password,
            'credentialsSentAt'    => $this->formatIso($l->credentials_sent_at),
            'quotationSentAt'      => $this->formatIso($l->quotation_sent_at),
            'quotationStatus'      => $l->quotation_status,
            'quotationApprovedAt'  => $this->formatIso($l->quotation_approved_at),
            'invoiceIssuedAt'      => $this->formatIso($l->invoice_issued_at),
            'initialDepositPaid'   => (bool) $l->initial_deposit_paid,
            'totalDepositPaid'     => (float) $l->total_deposit_paid,
            'paymentStatus'        => $l->payment_status ?: 'Unpaid',
            'reminderStage'        => (int) $l->reminder_stage,
            'reminderNextAt'       => $this->formatIso($l->reminder_next_at),
            'reminderContext'      => $l->reminder_context,
            'lastResponseAt'       => $this->formatIso($l->last_response_at),
            'surveyRequestedDate'      => $l->survey_requested_date,
            'surveyRequestedTimeRange' => $l->survey_requested_time_range,
            'surveyNotes'              => $l->survey_notes,
            'surveyStatus'             => $l->survey_status,
            'surveyType'               => $l->survey_type,
            'videoCallRoomId'          => $l->video_call_room_id,
            'videoCallStatus'          => $l->video_call_status ?: 'idle',
            'videoCallToken'           => $l->video_call_token,
            'videoCallCallerId'        => $l->video_call_caller_id,
            'videoCallCallerName'      => $l->video_call_caller_name,
            'videoCallCallerRole'      => $l->video_call_caller_role,
            'videoCallIsVideo'         => (bool) $l->video_call_is_video,
            'videoCallStartedAt'       => $this->formatIso($l->video_call_started_at),
            'videoCallEndedAt'         => $this->formatIso($l->video_call_ended_at),
            'surveyorName'             => $l->surveyor_name,
            'surveyorEmail'            => $l->surveyor_email,
            'surveyorReportNotes'      => $l->surveyor_report_notes,
            'surveyorMedia'            => array_values(array_unique(array_merge(
                is_string($l->surveyor_media) ? (json_decode($l->surveyor_media, true) ?: []) : ($l->surveyor_media ?: []),
                $l->relationLoaded('surveyMedia') 
                    ? $l->surveyMedia->pluck('file_url')->filter()->toArray() 
                    : []  // No N+1 fallback — rely on eager loading via ->with('surveyMedia')
            ))),
            'surveyorCompletedAt'      => $this->formatIso($l->surveyor_completed_at),
            'driverId'                 => $l->driver_id,
            'driverName'               => $l->driver_name,
            'driverEmail'              => $l->driver_email,
            'driverPhone'              => $l->driver_phone,
            'driverAssignedAt'         => $this->formatIso($l->driver_assigned_at),
            // ── Survey Report: Section C — Cargo & Move Details ──
            'propertyType'             => $l->property_type,
            'cargoVolume'              => $l->cargo_volume,
            'totalBoxes'               => $l->total_boxes,
            'recommendedVehicle'       => $l->recommended_vehicle,
            'packingService'           => $l->packing_service,
            'accessOrigin'             => $l->access_origin,
            // ── Survey Report: Section D — Access Requirements ──
            'liftAvailable'            => $l->lift_available,
            'parkingAvailable'         => $l->parking_available,
            'customAccessFields'       => is_string($l->custom_access_fields) ? (json_decode($l->custom_access_fields, true) ?: []) : ($l->custom_access_fields ?: []),
            // ── Survey Report: Section E — Dismantling ──
            'requiresDisassembly'      => $l->requires_disassembly,
            'bedsQuantity'             => $l->beds_quantity,
            'wardrobesQuantity'        => $l->wardrobes_quantity,
            'customDismantleItems'     => is_string($l->custom_dismantle_items) ? (json_decode($l->custom_dismantle_items, true) ?: []) : ($l->custom_dismantle_items ?: []),
            // ── Survey Report: Section F — Fragile Items ──
            'hasFragileItems'          => $l->has_fragile_items,
            'fineArtPaintings'         => $l->fine_art_paintings,
            'pianoAntiqueItems'        => $l->piano_antique_items,
            'customFragileFields'      => is_string($l->custom_fragile_fields) ? (json_decode($l->custom_fragile_fields, true) ?: []) : ($l->custom_fragile_fields ?: []),
            // ── Survey Report: Section G — Text Notes ──
            'specialInstructions'      => $l->special_instructions,
            'surveyorFindings'         => $l->surveyor_findings,
            // ── Other fields ──
            'rescheduleProposedDate'   => $l->reschedule_proposed_date,
            'rescheduleProposedTime'   => $l->reschedule_proposed_time,
            'rescheduleStatus'         => $l->reschedule_status,
            'rescheduleToken'          => $l->reschedule_token,
            'moveType'                 => $l->move_type,
            'leadType'                 => $l->lead_type,
            'from'                     => $l->from_location,
            'to'                       => $l->to_location,
            'moveDate'                 => $l->move_date?->format('Y-m-d'),
            'estValue'                 => (float) $l->est_value,
            'aiScore'                  => $l->ai_score,
            'priority'                 => $l->priority,
            'createdAt'                => $l->created_at ? \Carbon\Carbon::parse($l->created_at)->toIso8601String() : null,
            'createdAgo'               => $l->created_at ? $l->created_at->diffForHumans() : 'Just now',
        ];
    }

    private function clearLeadCache(): void
    {
        // Cache::flush() removed to prevent wiping entire system cache.
        // Index caching removed to ensure real-time CRM updates.
    }

    public function index(Request $request): JsonResponse
    {
        $query = Lead::query()->with('surveyMedia')->orderBy('created_at', 'desc');

        if ($month = $request->query('month')) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
                $query->whereYear('created_at', (int) $m[1])
                      ->whereMonth('created_at', (int) $m[2]);
            }
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Removed global caching to prevent stale CRM lists
        $leads = $query->get()->map(fn ($l) => $this->present($l))->toArray();

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $newId = 'L-' . mt_rand(100000, 99999999);

        // Manually-created leads land as drafts too, so the admin explicitly
        // approves them before any automation email goes out (spec #2/#3).
        $lead = Lead::create([
            'id'            => $newId,
            'name'          => $request->input('name'),
            'email'         => $request->input('email'),
            'phone'         => $request->input('phone'),
            'source'        => $request->input('source', 'Website Form'),
            'status'        => $request->input('status', 'draft'),
            'stage'         => 'New',
            'move_type'     => $request->input('moveType'),
            'lead_type'     => $request->input('leadType', 'domestic'),
            'from_location' => $request->input('from'),
            'to_location'   => $request->input('to'),
            'move_date'     => $request->input('moveDate', now()->addDays(30)->format('Y-m-d')),
            'est_value'     => $request->input('estValue', 0),
            'ai_score'      => $request->input('aiScore', 5),
            'priority'      => $request->input('priority', 'Warm'),
        ]);

        // Create or update the contact directory record so lead appears in Contacts section
        try {
            \App\Models\Contact::updateOrCreate(
                ['email' => $lead->email],
                [
                    'id'             => 'C-' . mt_rand(100000, 99999999),
                    'name'           => $lead->name,
                    'email'          => $lead->email,
                    'phone'          => $lead->phone,
                    'type'           => 'Customer',
                    'moves'          => 1,
                    'lifetime_value' => $lead->est_value ?: 1500,
                    'status'         => 'Active',
                ]
            );
        } catch (\Exception $e) {
            Log::warning("Failed to auto-create contact for lead {$lead->id}: " . $e->getMessage());
        }

        // Create the calendar job event for the move date.
        try {
            \App\Models\AppNotification::create([
                'user_id' => null,
                'type' => 'info',
                'title' => 'New Lead Received',
                'message' => 'A new lead (' . $lead->name . ') was received from ' . $lead->source . '.',
                'link' => '/app/leads?view=' . $lead->id,
                'is_read' => false,
            ]);

            \App\Models\JobEvent::create([
                'id'         => 'J-' . mt_rand(100000, 99999999),
                'title'      => $lead->name . ' — ' . ($lead->move_type ?: 'Move Job'),
                'type'       => 'Job',
                'status'     => 'booked',
                'lead_id'    => $lead->id,
                'event_date' => $lead->move_date ?: now()->format('Y-m-d'),
                'event_time' => '09:00',
                'driver'     => 'Unassigned',
                'vehicle'    => 'Unassigned',
                'location'   => ($lead->from_location && $lead->to_location) ? ($lead->from_location . ' → ' . $lead->to_location) : ($lead->from_location ?: 'TBC'),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to auto-create job event for lead {$lead->id}: " . $e->getMessage());
        }

        // Auto-draft indicative quotation based on lead details so Admin can review/edit/send
        try {
            $automation = new \App\Services\AutomationService();
            $automation->createIndicativeQuotation($lead);
        } catch (\Exception $e) {
            Log::warning("Failed to auto-create draft quotation for lead {$lead->id}: " . $e->getMessage());
        }

        $this->clearLeadCache();

        return response()->json($this->present($lead), 201);
    }

    /**
     * Admin approves a draft lead → welcome email + indicative quotation (spec #3).
     */
    public function approve(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $lead = $this->automation->approveLead($lead);
        $this->clearLeadCache();
        
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type' => 'success',
            'title' => 'Lead Approved',
            'message' => 'Lead (' . $lead->name . ') was approved. Welcome email sent.',
            'link' => '/app/leads?view=' . $lead->id,
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Lead approved — welcome email sent and indicative quotation drafted.',
            'lead'    => $this->present($lead),
        ]);
    }

    public function updateStage(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }
        $newStage = $request->input('stage');

        $updates = ['stage' => $newStage];
        switch ($newStage) {
            case 'Welcome Email':
                $updates['welcome_email_sent_at'] = now();
                break;
            case 'Quotation':
                $updates['quotation_sent_at'] = now();
                $updates['status'] = 'quoted';
                break;
            case 'Survey':
            case 'Request a Survey':
                $updates['survey_email_sent_at'] = now();
                break;
            case 'Book a Survey':
                $updates['survey_booked_at'] = now();
                $updates['status'] = 'survey-booked';
                break;
            case 'Invoice':
                $updates['invoice_issued_at'] = now();
                $updates['status'] = 'won';
                break;
        }

        $lead->update($updates);
        $this->clearLeadCache();

        return response()->json(['message' => 'Stage updated successfully', 'lead' => $this->present($lead)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $lead->update(array_filter([
            'status'    => $request->input('status'),
            'priority'  => $request->input('priority'),
            'est_value' => $request->input('estValue'),
        ], fn ($v) => $v !== null));
        
        $this->clearLeadCache();

        return response()->json(['message' => 'Lead updated successfully', 'lead' => $this->present($lead->fresh())]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) return response()->json(['error' => 'Not found'], 404);

        if ($request->has('status')) {
            $status = $request->input('status');
            // If the surveyor app sends 'in_progress', update survey_status
            if ($status === 'in_progress') {
                $lead->update(['survey_status' => 'in_progress']);
            } else {
                $lead->update(['status' => $status]);

                // Create or update the JobEvent so it immediately shows up in Jobs section
                if (in_array(strtolower($status), ['job', 'job-booked', 'booked', 'assigned', 'in_progress', 'won', 'completed'], true)) {
                    $job = \App\Models\JobEvent::where('lead_id', (string)$lead->id)->where('type', 'Job')->first();
                    $normalizedStatus = strtolower($status);
                    if (in_array($normalizedStatus, ['won', 'completed'], true)) {
                        $jobStatus = 'completed_won';
                    } elseif (in_array($normalizedStatus, ['not-completed', 'not_completed'], true)) {
                        $jobStatus = 'not_completed';
                    } elseif ($normalizedStatus === 'in_progress') {
                        $jobStatus = 'in_progress';
                    } elseif ($normalizedStatus === 'assigned') {
                        $jobStatus = 'assigned';
                    } else {
                        $jobStatus = 'booked';
                    }
                    if (!$job) {
                        \App\Models\JobEvent::create([
                            'id'         => 'J-' . mt_rand(100000, 99999999),
                            'lead_id'    => (string) $lead->id,
                            'type'       => 'Job',
                            'title'      => ($lead->name ?: 'Move Client') . ' — ' . ($lead->move_type ?: 'Move Job'),
                            'event_date' => $lead->move_date ?: now(),
                            'event_time' => $lead->arrival_window ?: '09:00 AM',
                            'driver'     => $lead->driver_name ?: 'Unassigned',
                            'vehicle'    => $lead->recommended_vehicle ?: 'Unassigned',
                            'location'   => trim(($lead->from_location ?: '') . ' → ' . ($lead->to_location ?: ''), ' →') ?: 'N/A',
                            'status'     => $jobStatus,
                        ]);
                    } else {
                        $job->update(['status' => $jobStatus]);
                    }
                }
            }
            
            \App\Models\AppNotification::create([
                'user_id' => null,
                'type'    => 'info',
                'title'   => 'Status Updated via App 📱',
                'message' => "Lead {$lead->name} status updated to: " . strtoupper($status) . ".",
                'link'    => '/app/leads?view=' . $lead->id,
                'is_read' => false,
            ]);
        }
        $this->clearLeadCache();
        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if ($lead) {
            $lead->delete(); // Soft delete
            $this->clearLeadCache();
            return response()->json(['message' => 'Lead moved to trash successfully', 'id' => $id]);
        }
        return response()->json(['error' => 'Lead not found'], 404);
    }

    public function trash(Request $request): JsonResponse
    {
        $leads = Lead::onlyTrashed()->orderBy('deleted_at', 'desc')->get()->map(fn ($l) => $this->present($l))->toArray();
        return response()->json($leads);
    }

    public function restore(string $id): JsonResponse
    {
        $lead = Lead::onlyTrashed()->find($id);
        if ($lead) {
            $lead->restore();
            $this->clearLeadCache();
            return response()->json(['message' => 'Lead restored successfully', 'lead' => $this->present($lead)]);
        }
        return response()->json(['error' => 'Lead not found in trash'], 404);
    }

    public function forceDelete(string $id): JsonResponse
    {
        $lead = Lead::withTrashed()->find($id);
        if ($lead) {
            $email = strtolower(trim($lead->email ?? ''));

            $emailQuery = \App\Models\Email::where('lead_id', $id);
            if (!empty($email)) {
                $emailQuery->orWhere('from_email', $email);
            }
            
            $messageIds = (clone $emailQuery)->pluck('message_id')->filter()->toArray();

            \Illuminate\Support\Facades\DB::table('deleted_leads')->insertOrIgnore([
                'lead_id'    => $id,
                'email'      => $email ?: null,
                'message_id' => !empty($messageIds) ? implode(',', $messageIds) : null,
                'deleted_at' => now(),
            ]);

            \App\Models\JobEvent::where('lead_id', $id)->delete();
            \App\Models\Quotation::where('lead_id', $id)->delete();
            
            $emailQuery->delete();
            
            if (!empty($email)) {
                \App\Models\Contact::where('email', $email)->delete();
            }
            $lead->forceDelete();
        } else {
            \Illuminate\Support\Facades\DB::table('deleted_leads')->insertOrIgnore([
                'lead_id'    => $id,
                'deleted_at' => now(),
            ]);
        }
        $this->clearLeadCache();

        return response()->json(['message' => 'Lead permanently deleted', 'id' => $id]);
    }

    /**
     * Public survey booking (from the /book-survey page). Records the customer's
     * chosen date/time/type and stops the survey reminder cycle (spec #6/#7).
     */
    public function submitSurveyRequest(Request $request): JsonResponse
    {
        $leadId = $request->input('lead_id');
        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $prefDate  = $request->input('preferred_date');
        $timeRange = $request->input('time_range', '10:00 AM');
        $surveyType = $request->input('survey_type', 'physical'); // physical | virtual | video

        $now = now();
        $lead->update([
            'survey_requested_date'       => $prefDate,
            'survey_requested_time_range' => $timeRange,
            'survey_notes'                => $request->input('notes'),
            'survey_type'                 => $surveyType,
            'survey_status'               => 'booked',
            'survey_requested_at'         => $now,
            'survey_booked_at'            => $now,
        ]);

        // Stop reminders + advance the funnel (records last_response_at, etc.).
        $this->automation->recordResponse($lead, 'survey');

        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'success',
            'title'   => 'Survey Booked 📅',
            'message' => "Client {$lead->name} submitted a pre-move survey request for " . ($prefDate ?: 'requested date') . " ({$timeRange}).",
            'link'    => '/app/surveys?view=' . $lead->id,
            'is_read' => false,
        ]);

        // Upsert the survey calendar event (no duplicates — keyed by lead+type).
        try {
            \App\Models\JobEvent::updateOrCreate(
                ['lead_id' => $lead->id, 'type' => 'Survey'],
                [
                    'id'         => 'SUR-' . $lead->id,
                    'title'      => "{$lead->name} — Pre-move Survey ({$surveyType})",
                    'status'     => 'booked',
                    'event_date' => $prefDate,
                    'event_time' => $timeRange,
                    'driver'     => 'Survey Team',
                    'vehicle'    => 'Survey Van',
                    'location'   => "{$lead->from_location} → {$lead->to_location}",
                ]
            );
        } catch (\Exception $e) {
            Log::warning("Failed to upsert survey calendar event: " . $e->getMessage());
        }

        $this->clearLeadCache();

        return response()->json([
            'success' => true,
            'message' => 'Survey request submitted successfully!',
        ]);
    }

    /**
     * Admin approves a scheduled survey (spec #7).
     */
    public function approveSurvey(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $lead = $this->automation->approveSurvey($lead);
        $this->clearLeadCache();

        return response()->json(['message' => 'Survey approved', 'lead' => $this->present($lead)]);
    }

    /**
     * Admin proposes a new date and exact time for a survey before approval.
     * POST /api/leads/{id}/propose-reschedule
     */
    public function proposeReschedule(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $request->validate([
            'proposedDate' => 'required|string',
            'proposedTime' => 'required|string',
        ]);

        $newDate = $request->input('proposedDate');
        $newTime = $request->input('proposedTime');

        $lead = $this->automation->proposeSurveyReschedule($lead, $newDate, $newTime);
        $this->clearLeadCache();

        return response()->json([
            'message' => 'Reschedule proposal sent to client',
            'lead'    => $this->present($lead),
        ]);
    }

    /**
     * Public endpoint: Client approves proposed survey reschedule from email link.
     * GET /api/survey/approve-reschedule?token=RSC-...
     */
    public function clientApproveReschedule(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 400);
        }

        $lead = $this->automation->confirmClientReschedule($token);
        if (!$lead) {
            return response()->json(['error' => 'Invalid or expired token'], 404);
        }

        $this->clearLeadCache();

        return response()->make("
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>Survey Schedule Confirmed — Next Gen Relocation</title>
                <style>
                    body { background: #0A0B0E; color: #FFFFFF; font-family: -apple-system, BlinkMacSystemFont, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                    .card { background: #14151C; border: 1px solid rgba(201,168,76,0.3); border-radius: 20px; padding: 40px; text-align: center; max-width: 500px; }
                    .badge { color: #C9A84C; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px; }
                    h1 { color: #FFFFFF; font-size: 24px; margin: 0 0 16px 0; }
                    p { color: #9CA3AF; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0; }
                    .highlight { color: #C9A84C; font-weight: 700; }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='badge'>SCHEDULE CONFIRMED</div>
                    <h1>Thank You, " . e($lead->name) . "</h1>
                    <p>You have approved the proposed survey schedule for <span class='highlight'>" . e($lead->survey_requested_date) . "</span> at <span class='highlight'>" . e($lead->survey_requested_time_range) . "</span>.</p>
                    <p>Our management team has been notified and will issue your final survey approval shortly.</p>
                </div>
            </body>
            </html>
        ", 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Admin assigns a surveyor to a lead's survey.
     */
    public function assignSurveyor(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $surveyorName = $request->input('surveyorName');
        $surveyorEmail = $request->input('surveyorEmail');

        $lead->update([
            'surveyor_name'  => $surveyorName,
            'surveyor_email' => $surveyorEmail,
            'survey_status'  => 'scheduled',
            'status'         => 'assigned',
        ]);
        
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type' => 'info',
            'title' => 'Survey Assigned',
            'message' => 'Survey for ' . $lead->name . ' assigned to ' . $surveyorName . '.',
            'link' => '/app/surveys?view=' . $lead->id,
            'is_read' => false,
        ]);

        if ($surveyorEmail) {
            $subject = "New Pre-move Survey Assignment: " . ($lead->name ?: 'Client');
            $bodyHtml = "
                <div class=\"category-badge\">SURVEY JOB ASSIGNMENT</div>
                <div class=\"headline\">New Pre-move Survey Assigned</div>
                <div class=\"salutation\">
                    Hello <strong>" . e($surveyorName ?: 'Surveyor') . "</strong>,<br/><br/>
                    You have been assigned a new pre-move survey client job. Please review the details below and update your schedule accordingly.
                </div>
                <div class=\"details-card\">
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">CLIENT NAME</div>
                        <div class=\"detail-value\">" . e($lead->name) . "</div>
                    </div>
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">CONTACT NUMBER</div>
                        <div class=\"detail-value\" style=\"color: #C9A84C; font-weight: bold;\">" . e($lead->phone ?: 'N/A') . "</div>
                    </div>
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">SURVEY DATE & TIME</div>
                        <div class=\"detail-value\" style=\"color: #C9A84C;\">" . e($lead->survey_requested_date ?: 'TBD') . " at " . e($lead->survey_requested_time_range ?: '10:00 AM') . "</div>
                    </div>
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">SURVEY MODE</div>
                        <div class=\"detail-value\" style=\"text-transform: capitalize;\">" . e($lead->survey_type ?: 'physical') . " Survey</div>
                    </div>
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">MOVE ROUTE</div>
                        <div class=\"detail-value\">" . e($lead->from_location ?: '—') . " → " . e($lead->to_location ?: '—') . "</div>
                    </div>
                </div>
                <div class=\"note-box\">
                    <p class=\"note-text\">You can view this assignment instantly along with your calendar schedule in your Surveyor Portal workspace or by launching the Next Gen Mobile App.</p>
                </div>
            ";

            try {
                \Illuminate\Support\Facades\Mail::to($surveyorEmail)->queue(
                    new \App\Mail\RawCustomEmail($subject, $bodyHtml)
                );
            } catch (\Throwable $e) {
                Log::warning("Failed to queue surveyor assignment email to {$surveyorEmail}: " . $e->getMessage());
            }

            // Also dispatch a push notification to the surveyor
            $surveyorUser = \App\Models\User::where('email', $surveyorEmail)->first();
            if ($surveyorUser) {
                \App\Jobs\SendPushNotificationJob::dispatch(
                    $surveyorUser->id,
                    'New Survey Assigned 📋',
                    "You've been assigned a new pre-move survey for " . ($lead->name ?: 'a client') . ".",
                    [
                        'type' => 'survey_assignment',
                        'lead_id' => (string) $lead->id,
                        'screen' => 'survey_duties'
                    ]
                );
            }
        }

        $this->clearLeadCache();

        return response()->json([
            'message' => 'Surveyor assigned successfully',
            'lead'    => $this->present($lead),
        ]);
    }

    /**
     * Get list of drivers available for assignment.
     */
    public function drivers(): JsonResponse
    {
        $users = \App\Models\User::select(['id', 'name', 'email', 'role'])
            ->orderByRaw("CASE WHEN role = 'driver' THEN 0 WHEN role = 'staff' THEN 1 ELSE 2 END")
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($users);
    }

    /**
     * Admin assigns a driver to a confirmed booking.
     */
    /**
     * Admin assigns a driver and schedules a job date & arrival time for a lead.
     */
    public function assignDriver(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $driverId    = $request->input('driverId') ?: $request->input('driver_id');
        $driverName  = $request->input('driverName') ?: ($request->input('driver_name') ?: $request->input('driver'));
        $driverEmail = $request->input('driverEmail') ?: $request->input('driver_email');
        $driverPhone = $request->input('driverPhone') ?: $request->input('driver_phone');
        $vehicle     = $request->input('vehicle') ?: ($request->input('recommended_vehicle') ?: $lead->recommended_vehicle);
        $eventDate   = $request->input('event_date') ?: ($request->input('move_date') ?: ($request->input('date') ?: ($lead->move_date?->format('Y-m-d') ?: null)));
        $eventTime   = $request->input('event_time') ?: ($request->input('arrival_window') ?: ($request->input('time') ?: ($lead->arrival_window ?: '09:00 AM')));
        $notes       = $request->input('notes');

        if (!$driverName && $driverId) {
            $user = \App\Models\User::find($driverId);
            if ($user) {
                $driverName  = $user->name;
                $driverEmail = $driverEmail ?: $user->email;
            }
        }

        $scheduleToken = $lead->job_schedule_token ?: md5($lead->id . '_' . time());

        $publicBase = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');
        $clientConfirmUrl = "{$publicBase}/job/schedule/confirm?token={$scheduleToken}&role=client";
        $clientRescheduleUrl = "{$publicBase}/job/schedule/reschedule?token={$scheduleToken}&role=client";
        $driverConfirmUrl = "{$publicBase}/job/schedule/confirm?token={$scheduleToken}&role=driver";
        $driverRescheduleUrl = "{$publicBase}/job/schedule/reschedule?token={$scheduleToken}&role=driver";

        $leadUpdates = [
            'driver_id'          => $driverId,
            'driver_name'        => $driverName,
            'driver_email'       => $driverEmail,
            'driver_phone'       => $driverPhone,
            'driver_assigned_at' => now(),
            'status'             => 'assigned',
            'stage'              => 'Job',
            'job_schedule_token' => $scheduleToken,
            'client_job_status'  => 'pending',
            'driver_job_status'  => 'pending',
        ];
        if ($vehicle) {
            $leadUpdates['recommended_vehicle'] = $vehicle;
        }
        if ($eventDate) {
            $leadUpdates['move_date'] = $eventDate;
        }
        if ($eventTime) {
            $leadUpdates['arrival_window'] = $eventTime;
        }
        $lead->update($leadUpdates);

        // Sync or Create JobEvent record
        $job = \App\Models\JobEvent::where('lead_id', (string) $lead->id)->where('type', 'Job')->first();
        $jobId = $job?->id ?: ('J-' . mt_rand(100000, 99999999));
        $jobData = [
            'driver'             => $driverName ?: 'Assigned Driver',
            'vehicle'            => $vehicle ?: 'Removal Van',
            'status'             => 'assigned',
            'event_date'         => $eventDate ?: ($lead->move_date ?: now()),
            'event_time'         => $eventTime ?: '09:00 AM',
            'job_schedule_token' => $scheduleToken,
            'client_job_status'  => 'pending',
            'driver_job_status'  => 'pending',
        ];
        if ($notes) {
            $jobData['notes'] = $notes;
        }

        if ($job) {
            $job->update($jobData);
        } else {
            $job = \App\Models\JobEvent::create(array_merge([
                'id'         => $jobId,
                'lead_id'    => (string) $lead->id,
                'type'       => 'Job',
                'title'      => $lead->name . ' — ' . ($lead->move_type ?: 'Move Job'),
                'location'   => trim(($lead->from_location ?: '') . ' → ' . ($lead->to_location ?: ''), ' →') ?: 'N/A',
            ], $jobData));
        }

        // 1. Send detailed email to Assigned Driver
        if ($driverEmail) {
            $subject = "🚚 Job Assignment & Arrival Schedule — Job #" . $jobId;
            $formattedMoveDate = $eventDate ? \Carbon\Carbon::parse($eventDate)->format('d M Y') : 'Scheduled Date';
            $bodyHtml = "
                <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #c9a84c;\">
                    <h2 style=\"color: #c9a84c; margin-top: 0;\">Next Gen Relocation — Driver Job Assignment</h2>
                    <p style=\"font-size: 14px;\">Hello <strong>" . e($driverName ?: 'Driver') . "</strong>,</p>
                    <p style=\"font-size: 14px;\">You have been assigned a relocation move. Please review your scheduled date, time, and client details below:</p>
                    
                    <div style=\"background-color: #141722; border: 1px solid #c9a84c; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">⏰ Schedule & Vehicle</h3>
                        <p style=\"margin: 6px 0; font-size: 14px; font-weight: bold; color: #f59e0b;\">📅 Relocation Date: " . e($formattedMoveDate) . "</p>
                        <p style=\"margin: 6px 0; font-size: 14px; font-weight: bold; color: #10b981;\">⏰ Scheduled Arrival Time: " . e($eventTime) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Assigned Vehicle:</strong> " . e($vehicle ?: 'Removal Van') . "</p>
                    </div>

                    <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📋 Client & Route Overview</h3>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Job ID:</strong> " . e($jobId) . " (Lead #" . e($lead->id) . ")</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Name:</strong> " . e($lead->name) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Phone:</strong> " . e($lead->phone ?: 'N/A') . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Email:</strong> " . e($lead->email ?: 'N/A') . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Pickup Address:</strong> " . e($lead->from_location ?: 'TBD') . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Destination Address:</strong> " . e($lead->to_location ?: 'TBD') . "</p>
                        " . ($notes ? "<p style=\"margin: 6px 0; font-size: 13px;\"><strong>Special Instructions:</strong> " . e($notes) . "</p>" : "") . "
                    </div>

                    <p style=\"font-size: 13px; color: #94a3b8;\">Please ensure you are at the pickup location on time. Access your Driver App for live navigation and status updates.</p>
                </div>
            ";

            try {
                \Illuminate\Support\Facades\Mail::to($driverEmail)->queue(
                    new \App\Mail\RawCustomEmail($subject, $bodyHtml)
                );
            } catch (\Throwable $e) {
                Log::warning("Failed to queue driver assignment email to {$driverEmail}: " . $e->getMessage());
            }

            // Dispatch push notification to driver app
            if ($driverId) {
                $driverUser = \App\Models\User::find($driverId);
                if ($driverUser) {
                    \App\Jobs\SendPushNotificationJob::dispatch(
                        $driverUser->id,
                        'New Job Assigned 🚚',
                        "You've been assigned a new job for " . ($lead->name ?: 'a client') . ".",
                        [
                            'type' => 'job_assignment',
                            'job_id' => (string) $jobId,
                            'screen' => 'job_detail'
                        ]
                    );
                }
            }
        }

        // 2. Send schedule confirmation email to Client (instructing them to be present at the specified date & time)
        if ($lead->email) {
            $clientSubject = "🚚 Your Relocation Move Scheduled & Driver Assigned — #" . $lead->id;
            $formattedMoveDate = $eventDate ? \Carbon\Carbon::parse($eventDate)->format('d M Y') : ($lead->move_date ? \Carbon\Carbon::parse($lead->move_date)->format('d M Y') : 'Scheduled Date');
            $clientBodyHtml = "
                <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #c9a84c;\">
                    <h2 style=\"color: #c9a84c; margin-top: 0;\">Next Gen Relocation — Move Schedule Confirmation</h2>
                    <p style=\"font-size: 14px;\">Dear <strong>" . e($lead->name) . "</strong>,</p>
                    <p style=\"font-size: 14px;\">Your upcoming relocation move has been official scheduled and assigned to our lead driver team.</p>
                    
                    <div style=\"background-color: #141722; border: 1px solid #c9a84c; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">⏰ Scheduled Move Date & Driver Arrival Window</h3>
                        <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #f59e0b;\">📅 Relocation Date: " . e($formattedMoveDate) . "</p>
                        <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #10b981;\">⏰ Driver Arrival Time: " . e($eventTime) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Assigned Lead Driver:</strong> " . e($driverName ?: 'Lead Driver') . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Allocated Vehicle:</strong> " . e($vehicle ?: 'Removal Van') . "</p>
                    </div>

                    <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📍 Pickup & Destination Address</h3>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Pickup Location:</strong> " . e($lead->from_location ?: 'TBD') . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Destination Location:</strong> " . e($lead->to_location ?: 'TBD') . "</p>
                    </div>

                    <div style=\"background-color: #1e2538; border-left: 4px solid #f59e0b; padding: 14px; border-radius: 6px; margin: 18px 0;\">
                        <p style=\"margin: 0; font-size: 13px; font-weight: bold; color: #f59e0b;\">⚠️ Important Availability Notice:</p>
                        <p style=\"margin: 4px 0 0 0; font-size: 13px;\">Please ensure that you or an authorized representative are present at the pickup location on <strong>" . e($formattedMoveDate) . " at " . e($eventTime) . "</strong> for loading, key access, and item verification.</p>
                    </div>

                    <p style=\"font-size: 13px; color: #94a3b8;\">If you need to update any access details or contact your move coordinator, reply directly to this email or contact customer support.</p>
                </div>
            ";

            try {
                \Illuminate\Support\Facades\Mail::to($lead->email)->queue(
                    new \App\Mail\RawCustomEmail($clientSubject, $clientBodyHtml)
                );
            } catch (\Throwable $e) {
                Log::warning("Failed to queue customer move schedule email to {$lead->email}: " . $e->getMessage());
            }
        }

        // Notify Admin / System
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'info',
            'title'   => 'Job Scheduled & Driver Assigned',
            'message' => "Job scheduled for " . ($eventDate ?: 'Scheduled Date') . " at " . ($eventTime ?: '09:00 AM') . ". Driver " . ($driverName ?: 'Unknown') . " assigned to Lead {$lead->id}.",
            'link'    => '/app/jobs?view=' . $jobId,
            'is_read' => false,
        ]);

        $this->clearLeadCache();

        return response()->json([
            'message' => 'Job scheduled successfully & notifications sent to client and driver.',
            'lead'    => $this->present($lead->fresh()),
            'job'     => $job,
        ]);
    }

    public function uploadMedia(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) return response()->json(['error' => 'Lead not found'], 404);

        $urls = [];
        if ($request->hasFile('files')) {
            $files = $request->file('files');
            if (!is_array($files)) $files = [$files];
            foreach ($files as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                $type = $isVideo ? 'survey-videos' : 'survey-images';
                $filename = $type . '-' . $lead->id . '-' . time() . '-' . uniqid() . '.' . ($isVideo ? $ext : 'jpg');
                
                $path = $file->storeAs($type, $filename, 'public');
                $url = url(\Illuminate\Support\Facades\Storage::url($path));
                $urls[] = str_replace('http://localhost', 'http://127.0.0.1:8000', $url); // Ensure correct dev url if needed
            }
        }

        return response()->json([
            'success' => true,
            'media_urls' => $urls
        ]);
    }

    /**
     * Surveyor panel submits survey report (notes & uploaded media URLs/Base64).
     */
    public function submitSurveyReport(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $request->validate([
            'reportNotes'   => 'nullable|string',
            'media'         => 'nullable',
            'media.*'       => 'nullable', // can be files or base64 strings
            'media_files.*' => 'nullable|file|max:51200',
            'files.*'       => 'nullable|file|max:51200',
            'images.*'      => 'nullable|file|max:20480', // 20MB max per photo
            'videos.*'      => 'nullable|file|max:102400',// 100MB max per video
        ]);

        \Illuminate\Support\Facades\Log::info('submitSurveyReport payload for lead ' . $id, [
            'input' => $request->all(),
            'files' => array_keys($request->allFiles()),
        ]);

        // Handle pre-uploaded URLs or string array
        $mediaUrls = [];
        $inputMedia = $request->input('media') ?: $request->input('remote_media_urls');
        if (is_array($inputMedia)) {
            foreach ($inputMedia as $m) {
                if (is_string($m)) {
                    $mediaUrls[] = $m;
                }
            }
        } elseif (is_string($inputMedia)) {
            $mediaUrls[] = $inputMedia;
        }

        // Handle direct file uploads (multipart/form-data)
        $fileKeys = ['media', 'media_files', 'files', 'images', 'videos'];
        foreach ($fileKeys as $key) {
            if ($request->hasFile($key)) {
                $files = $request->file($key);
                if (!is_array($files)) {
                    $files = [$files];
                }
                
                $manager = class_exists(\Intervention\Image\ImageManager::class) 
                    ? new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()) 
                    : null;

                foreach ($files as $file) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                    $type = $isVideo ? 'survey-videos' : 'survey-images';
                    
                    $filename = $type . '-' . $lead->id . '-' . time() . '-' . uniqid() . '.' . ($isVideo ? $ext : 'jpg');
                    $path = $type . '/' . $filename;

                    if (!$isVideo && $manager) {
                        try {
                            // Compress image
                            $image = $manager->read($file->getRealPath());
                            $image->scaleDown(width: 1600); // Prevent massive 4K images
                            $encoded = $image->toJpeg(75); // Compress quality to 75%
                            
                            \Illuminate\Support\Facades\Storage::disk('public')->put($path, (string) $encoded);
                            $mediaUrls[] = \Illuminate\Support\Facades\Storage::url($path);
                            continue;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Image compression failed: " . $e->getMessage());
                            // Fallback to normal upload
                        }
                    }

                    // Normal upload (videos or if image compression failed)
                    $file->storeAs($type, $filename, 'public');
                    $mediaUrls[] = \Illuminate\Support\Facades\Storage::url($path);
                }
            }
        }

        $mediaUrls   = array_values(array_unique($mediaUrls));

        // ── Parse JSON dynamic fields that may arrive as strings or arrays ──
        $customAccessFields   = $request->input('custom_access_fields');
        $customDismantleItems = $request->input('custom_dismantle_items') ?: $request->input('items');
        $customFragileFields  = $request->input('custom_fragile_fields');
        if (is_string($customAccessFields))   $customAccessFields   = json_decode($customAccessFields, true);
        if (is_string($customDismantleItems)) $customDismantleItems = json_decode($customDismantleItems, true);
        if (is_string($customFragileFields))  $customFragileFields  = json_decode($customFragileFields, true);

        // Clean up surveyor_findings: strip out any nested report template headers if Flutter sent full report text in findings
        $findingsInput = trim($request->input('surveyor_findings') ?: '');
        $cleanFindings = preg_replace('/={10,}[\s\S]*?={10,}/u', '', $findingsInput);
        $cleanFindings = trim(preg_replace('/\n{3,}/', "\n\n", $cleanFindings));
        if (empty($cleanFindings)) {
            $cleanFindings = $findingsInput && !str_contains($findingsInput, 'HOUSE RELOCATION') ? $findingsInput : null;
        }

        // Build clean, beautifully structured report notes summary
        $rawReportNotes = $request->input('reportNotes') ?: $request->input('report_notes') ?: $request->input('surveyor_notes');
        $reportNotes = $this->buildStructuredReportNotes($lead, $request, $cleanFindings, $rawReportNotes);

        // ── Detect re-submission (edit/update) vs. first submission ──
        $isResubmission = !empty($lead->surveyor_completed_at);

        $lead->update([
            // ── Status & Lifecycle ──
            'surveyor_report_notes' => $reportNotes,
            'surveyor_media'        => json_encode($mediaUrls),
            // Preserve the original completion timestamp on re-submissions
            'surveyor_completed_at' => $isResubmission ? $lead->surveyor_completed_at : now(),
            'survey_status'         => 'completed',
            'status'                => 'survey-completed',
            'stage'                 => 'Survey Completed',

            // ── Section C: House Shift & Cargo ──
            'property_type'         => $request->input('property_type'),
            'cargo_volume'          => $request->input('cargo_volume'),
            'total_boxes'           => $request->input('total_boxes'),
            'recommended_vehicle'   => $request->input('recommended_vehicle'),
            'packing_service'       => $request->input('packing_service') ?: $request->input('packing_type'),
            'access_origin'         => $request->input('access_origin') ?: $request->input('floors_origin'),

            // ── Section D: Access Requirements ──
            'lift_available'        => $request->input('lift_available') ?? $request->input('lift_origin'),
            'parking_available'     => $request->input('parking_available'),
            'custom_access_fields'  => $customAccessFields ? json_encode($customAccessFields) : null,

            // ── Section E: Dismantling ──
            'requires_disassembly'  => $request->input('requires_disassembly'),
            'beds_quantity'         => $request->input('beds_quantity'),
            'wardrobes_quantity'    => $request->input('wardrobes_quantity'),
            'custom_dismantle_items'=> $customDismantleItems ? json_encode($customDismantleItems) : null,

            // ── Section F: Fragile Items ──
            'has_fragile_items'     => $request->input('has_fragile_items'),
            'fine_art_paintings'    => $request->input('fine_art_paintings'),
            'piano_antique_items'   => $request->input('piano_antique_items'),
            'custom_fragile_fields' => $customFragileFields ? json_encode($customFragileFields) : null,

            // ── Section G: Text Notes ──
            'special_instructions'  => $request->input('special_instructions'),
            'surveyor_findings'     => $request->input('surveyor_findings'),
        ]);

        // Only auto-draft the post-survey quotation on the FIRST submission.
        // On re-submissions (edits), just skip this step to avoid duplicates.
        if (!$isResubmission) {
            try {
                $this->automation->createPostSurveyQuotation($lead->fresh());
            } catch (\Throwable $e) {
                Log::warning("Could not create post-survey quotation for lead {$lead->id}: " . $e->getMessage());
            }
        } else {
            // On re-submission, update the existing draft post-survey quotation's
            // findings/notes fields so the admin sees the latest data.
            try {
                $existingDraftQuote = \App\Models\Quotation::where('lead_id', $lead->id)
                    ->where('quote_type', 'post-survey')
                    ->whereIn('status', ['draft', 'sent'])
                    ->latest()
                    ->first();
                if ($existingDraftQuote) {
                    $existingDraftQuote->update([
                        'surveyor_findings'    => $request->input('surveyor_findings'),
                        'special_instructions' => $request->input('special_instructions'),
                        'report_notes'         => $reportNotes,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Could not update existing post-survey quotation for lead {$lead->id}: " . $e->getMessage());
            }
        }

        $this->clearLeadCache();

        $action = $isResubmission ? 'Survey report updated successfully.' : 'Survey report submitted & lead moved to Survey Completed.';

        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'success',
            'title'   => 'Survey Report Submitted 📋',
            'message' => "Survey report for {$lead->name} has been " . ($isResubmission ? 'updated' : 'submitted') . ".",
            'link'    => '/app/surveys?view=' . $lead->id,
            'is_read' => false,
        ]);

        return response()->json([
            'message'       => $action,
            'is_update'     => $isResubmission,
            'lead'          => $this->present($lead->fresh()),
        ]);
    }


    /**
     * Return all 4 survey-day reminder records for a lead.
     * GET /api/leads/{id}/survey-reminders
     */
    public function surveyReminders(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $reminders = SurveyReminder::where('lead_id', $id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'type'        => $r->type,
                'label'       => $r->label,
                'scheduledAt' => $r->scheduled_at ? \Carbon\Carbon::parse($r->scheduled_at)->toIso8601String() : null,
                'status'      => $r->status,
                'sentAt'      => $r->sent_at ? \Carbon\Carbon::parse($r->sent_at)->toIso8601String() : null,
            ]);

        return response()->json($reminders);
    }

    /**
     * Skip one specific survey reminder (admin control).
     * POST /api/leads/{id}/survey-reminders/{reminderId}/skip
     */
    public function skipSurveyReminder(string $id, int $reminderId): JsonResponse
    {
        $reminder = SurveyReminder::where('id', $reminderId)
            ->where('lead_id', $id)
            ->first();

        if (!$reminder) {
            return response()->json(['error' => 'Reminder not found'], 404);
        }

        if ($reminder->status !== 'pending') {
            return response()->json(['error' => 'Only pending reminders can be skipped'], 422);
        }

        $reminder->update(['status' => 'skipped']);

        return response()->json(['message' => 'Reminder skipped', 'id' => $reminderId]);
    }

    /**
     * Stop all remaining pending survey reminders for a lead.
     * POST /api/leads/{id}/survey-reminders/stop
     */
    public function stopSurveyReminders(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $count = SurveyReminder::where('lead_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'stopped']);

        Log::info("Lead {$id}: {$count} survey reminder(s) stopped by admin.");

        return response()->json(['message' => "{$count} pending reminder(s) stopped"]);
    }

    /**
     * Return all quotation reminder records for a lead.
     * GET /api/leads/{id}/quotation-reminders
     */
    public function quotationReminders(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $latestQuote = \App\Models\Quotation::where('lead_id', $lead->id)->orderByDesc('created_at')->first();
        if (!$latestQuote) {
            return response()->json([]);
        }

        $reminders = \App\Models\QuotationReminder::where('quotation_id', $latestQuote->id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'label'       => $r->label,
                'scheduledAt' => $r->scheduled_at ? \Carbon\Carbon::parse($r->scheduled_at)->toIso8601String() : null,
                'status'      => $r->status,
                'sentAt'      => $r->sent_at ? \Carbon\Carbon::parse($r->sent_at)->toIso8601String() : null,
            ]);

        return response()->json($reminders);
    }

    /**
     * Skip one specific quotation reminder.
     * POST /api/leads/{id}/quotation-reminders/{reminderId}/skip
     */
    public function skipQuotationReminder(string $id, int $reminderId): JsonResponse
    {
        $reminder = QuotationReminder::where('id', $reminderId)
            ->where('lead_id', $id)
            ->first();

        if (!$reminder) {
            return response()->json(['error' => 'Reminder not found'], 404);
        }

        if ($reminder->status !== 'pending') {
            return response()->json(['error' => 'Only pending reminders can be skipped'], 422);
        }

        $reminder->update(['status' => 'skipped']);

        return response()->json(['message' => 'Quotation reminder skipped', 'id' => $reminderId]);
    }

    /**
     * Stop all remaining pending quotation reminders for a lead.
     * POST /api/leads/{id}/quotation-reminders/stop
     */
    public function stopQuotationReminders(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $count = QuotationReminder::where('lead_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'stopped']);

        Log::info("Lead {$id}: {$count} quotation reminder(s) stopped by admin.");

        return response()->json(['message' => "{$count} pending quotation reminder(s) stopped"]);
    }

    /**
     * Update the scheduled time for a survey reminder.
     * PUT /api/leads/{id}/survey-reminders/{reminderId}
     */
    public function updateSurveyReminder(Request $request, string $id, int $reminderId): JsonResponse
    {
        $reminder = SurveyReminder::where('id', $reminderId)
            ->where('lead_id', $id)
            ->first();

        if (!$reminder) {
            return response()->json(['error' => 'Reminder not found'], 404);
        }

        $request->validate([
            'scheduledAt' => 'required|date',
        ]);

        $reminder->update([
            'scheduled_at' => $request->input('scheduledAt'),
        ]);

        return response()->json([
            'message' => 'Survey reminder time updated',
            'reminder' => [
                'id' => $reminder->id,
                'scheduledAt' => $reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->toIso8601String() : null,
            ]
        ]);
    }

    /**
     * Update the scheduled time for a quotation reminder.
     * PUT /api/leads/{id}/quotation-reminders/{reminderId}
     */
    public function updateQuotationReminder(Request $request, string $id, int $reminderId): JsonResponse
    {
        $reminder = QuotationReminder::where('id', $reminderId)
            ->where('lead_id', $id)
            ->first();

        if (!$reminder) {
            return response()->json(['error' => 'Reminder not found'], 404);
        }

        $request->validate([
            'scheduledAt' => 'required|date',
        ]);

        $reminder->update([
            'scheduled_at' => $request->input('scheduledAt'),
        ]);

        return response()->json([
            'message' => 'Quotation reminder time updated',
            'reminder' => [
                'id' => $reminder->id,
                'scheduledAt' => $reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->toIso8601String() : null,
            ]
        ]);
    }

    /**
     * Admin/Surveyor triggers outgoing video call.
     * POST /api/leads/{id}/video-call/start
     */
    public function startVideoCall(Request $request, string $id): JsonResponse
    {
        $targetId = $id;
        if (!$targetId || $targetId === '{id}' || $targetId === 'undefined') {
            $targetId = $request->input('lead_id') ?: $request->input('leadId');
        }

        $lead = null;
        if ($targetId) {
            $lead = Lead::find($targetId);
        }
        if (!$lead) {
            return response()->json(['error' => 'Lead not found', 'success' => false], 404);
        }

        // Auto-expire stale ringing sessions older than 35s so a fresh call can start.
        if (in_array($lead->video_call_status, ['ringing', 'in_progress'], true)) {
            $startedAt = $lead->video_call_started_at;
            $isStale   = !$startedAt || $startedAt->isBefore(now()->subSeconds(35));
            if ($isStale) {
                $lead->update([
                    'video_call_status'   => 'idle',
                    'video_call_ended_at' => now(),
                ]);
                $this->syncCallSession($lead->fresh(), $request, 'missed', 'no_answer');
                $this->clearLeadCache();
                $lead->refresh();
                Log::info("Lead {$lead->id}: Auto-expired stale '{$lead->video_call_status}' call before new start.");
            } else {
                return response()->json([
                    'success' => false,
                    'error'   => 'A call is already active for this lead.',
                    'code'    => 'CALL_ALREADY_ACTIVE',
                ], 409);
            }
        }

        $callerName = $request->input('caller_name') ?: ($request->input('callerName') ?: 'Surveyor');
        $callerRole = $request->input('caller_role') ?: ($request->input('callerRole') ?: 'surveyor');
        $callerId   = (string) ($request->input('caller_id') ?: ($request->input('callerId') ?: ''));
        $isVideo    = filter_var(
            $request->input('is_video') ?? $request->input('isVideo') ?? true,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
        $targetRole = $request->input('target_role') ?: ($request->input('targetRole') ?: 'admin');
        $targetUserId = $request->input('target_user_id') ?: ($request->input('targetUserId') ?: null);

        $roomId = $request->input('roomId') ?: ('ROOM-' . $lead->id . '-' . strtoupper(substr(uniqid(), -4)));
        $token  = 'TOK-' . bin2hex(random_bytes(16));

        $lead->update([
            'video_call_room_id'     => $roomId,
            'video_call_status'      => 'ringing',
            'video_call_token'       => $token,
            'video_call_caller_id'   => $callerId ?: null,
            'video_call_caller_name' => $callerName,
            'video_call_caller_role' => $callerRole,
            'video_call_is_video'    => $isVideo,
            'video_call_started_at'  => now(),
            'video_call_ended_at'    => null,
        ]);

        $this->syncCallSession($lead->fresh(), $request, 'ringing');
        $this->clearLeadCache();

        Log::info("Lead {$lead->id}: Video call initiated by {$callerName} ({$callerRole}) -> {$targetRole}. Room: {$roomId}");

        return response()->json([
            'success'    => true,
            'status'     => 'ringing',
            'roomId'     => $roomId,
            'leadId'     => $lead->id,
            'zegoAppId'  => (int) config('services.zego.app_id'),
            'zegoAppSign' => config('services.zego.app_sign'),
            'is_video'   => $isVideo,
            'isRinging'  => true,
            'callerName' => $callerName,
            'callerRole' => $callerRole,
            'message'    => 'Video call initiated successfully',
            'call'       => [
                'roomId'           => $roomId,
                'status'           => 'ringing',
                'isVideo'          => $isVideo,
                'clientToken'      => $token,
                'zegoAppId'        => (int) config('services.zego.app_id'),
                'zegoAppSign'      => config('services.zego.app_sign'),
                'zegoServerSecret' => config('services.zego.server_secret'),
            ],
        ]);
    }

    /**
     * Mobile App polls the current call state every two seconds.
     * GET /api/leads/{id}/video-call/status
     */
    public function getCallStatus(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'error' => 'Lead not found'], 404);
        }

        if ($lead->video_call_status === 'ringing' && $lead->video_call_started_at?->isBefore(now()->subSeconds(30))) {
            $lead->update([
                'video_call_status' => 'missed',
                'video_call_ended_at' => now(),
            ]);
            $this->clearLeadCache();
            $this->syncCallSession($lead->fresh(), request(), 'missed', 'no_answer');
        }

        return response()->json([
            'success' => true,
            'status' => $lead->fresh()->video_call_status ?: 'idle',
            'roomId' => $lead->video_call_room_id,
            'zegoAppId' => (int) config('services.zego.app_id'),
            'zegoAppSign' => config('services.zego.app_sign'),
            'isVideo' => (bool) $lead->video_call_is_video,
        ]);
    }

    /**
     * Admin accepts, declines, or ends a mobile-originated call.
     * POST /api/leads/{id}/video-call/action
     */
    public function videoCallAction(Request $request, string $id): JsonResponse
    {
        $request->validate(['action' => 'required|in:accept,decline,end']);
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'error' => 'Lead not found'], 404);
        }

        $status = match ($request->input('action')) {
            'accept' => 'in_progress',
            'decline' => 'declined',
            'end' => 'completed',
        };

        $updates = ['video_call_status' => $status];
        if (in_array($status, ['declined', 'completed'], true)) {
            $updates['video_call_ended_at'] = now();
        }
        $lead->update($updates);
        $this->syncCallSession($lead->fresh(), $request, $status, $status === 'declined' ? 'declined' : ($status === 'completed' ? 'hangup' : null));
        $this->clearLeadCache();

        return response()->json([
            'success' => true,
            'status' => $status,
            'roomId' => $lead->video_call_room_id,
            'zegoAppId' => (int) config('services.zego.app_id'),
            'zegoAppSign' => config('services.zego.app_sign'),
        ]);
    }

    /**
     * Admin/Customer polls call status or updates call status (ringing | in_progress | ended | missed | declined).
     * POST /api/leads/{id}/video-call/status
     */
    public function syncCallStatus(Request $request, string $id): JsonResponse
    {
        $targetId = $id;
        if (!$targetId || $targetId === '{id}' || $targetId === 'undefined') {
            $targetId = $request->input('lead_id') ?: $request->input('leadId');
        }

        $lead = Lead::find($targetId);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found', 'success' => false], 404);
        }

        $newStatus = $request->input('status');
        if ($newStatus && in_array($newStatus, ['idle', 'ringing', 'in_progress', 'ended', 'missed', 'declined'])) {
            $updates = ['video_call_status' => $newStatus];
            if (in_array($newStatus, ['ended', 'missed', 'declined', 'idle'])) {
                $updates['video_call_ended_at'] = now();
            }
            $lead->update($updates);
            $this->syncCallSession($lead->fresh(), $request, $newStatus, in_array($newStatus, ['ended', 'idle'], true) ? 'hangup' : ($newStatus ?: null));
            $this->clearLeadCache();
        }

        // Save notes if provided
        if ($request->has('notes')) {
            $notesVal = $request->input('notes');
            $lead->update([
                'surveyor_report_notes' => $notesVal,
                'survey_notes'          => $notesVal,
            ]);
            $this->clearLeadCache();
        }

        $isRinging = $lead->video_call_status === 'ringing';

        return response()->json([
            'success'   => true,
            'isRinging' => $isRinging,
            'status'    => $lead->video_call_status ?: 'idle',
            'lead'      => $this->present($lead->fresh()),
            'call'      => [
                'roomId' => $lead->video_call_room_id,
                'status' => $lead->video_call_status ?: 'idle',
            ],
        ]);
    }

    /**
     * Save/update notes captured during or after a video call survey.
     * POST /api/leads/{id}/video-call/notes
     */
    public function saveVideoCallNotes(Request $request, string $id): JsonResponse
    {
        $targetId = $id;
        if (!$targetId || $targetId === '{id}' || $targetId === 'undefined') {
            $targetId = $request->input('lead_id') ?: $request->input('leadId');
        }

        $lead = Lead::find($targetId);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found', 'success' => false], 404);
        }

        $notes = $request->input('notes', '');
        $lead->update([
            'surveyor_report_notes' => $notes,
            'survey_notes'          => $notes,
        ]);

        $this->clearLeadCache();

        Log::info("Lead {$lead->id}: Video call survey notes saved/updated (" . strlen($notes) . " chars)");

        return response()->json([
            'success' => true,
            'message' => 'Video call survey notes saved successfully',
            'notes'   => $notes,
            'lead'    => $this->present($lead->fresh()),
        ]);
    }

    /**
     * Mobile App API: App polls every 2s to check for active incoming call.
     * GET /api/customer/video-call/verify?lead_id=L-1234&token=TOK-...
     */
    public function verifyCustomerCall(Request $request): JsonResponse
    {
        $leadId = $request->query('lead_id') ?: ($request->query('leadId') ?: $request->input('lead_id'));
        $token  = $request->query('token');
        $email  = $request->query('email') ?: ($request->query('user_email') ?: $request->query('surveyor_email'));
        $userId = $request->query('user_id') ?: ($request->query('userId') ?: $request->query('surveyor_id'));

        if ($userId && !$email) {
            $userObj = \App\Models\User::find($userId);
            if ($userObj && $userObj->email) {
                $email = $userObj->email;
            }
        }

        $query = Lead::query();
        if ($leadId && $leadId !== '{id}' && $leadId !== 'undefined') {
            $query->where('id', $leadId);
        } elseif ($token) {
            $query->where('video_call_token', $token);
        } elseif ($email) {
            $query->where(function ($q) use ($email) {
                $q->where('email', strtolower(trim($email)))
                  ->orWhere('surveyor_email', strtolower(trim($email)));
            });
        } else {
            // Find any active ringing call for mobile app / web listener
            $query->whereIn('video_call_status', ['ringing', 'in_progress']);
        }

        $lead = $query->orderBy('video_call_started_at', 'desc')->orderBy('created_at', 'desc')->first();

        // Fallback: If no lead matched or matched lead is idle, check for ANY ringing lead in system
        if (!$lead || !in_array($lead->video_call_status, ['ringing', 'in_progress'])) {
            $ringingLead = Lead::where('video_call_status', 'ringing')->orderBy('video_call_started_at', 'desc')->orderBy('created_at', 'desc')->first();
            if ($ringingLead) {
                $lead = $ringingLead;
            }
        }

        // Check if ringing call timed out (35 seconds since call started)
        if ($lead && $lead->video_call_status === 'ringing' && $lead->video_call_started_at) {
            if ($lead->video_call_started_at->isBefore(now()->subSeconds(35))) {
                $lead->update([
                    'video_call_status'   => 'missed',
                    'video_call_ended_at' => now(),
                ]);
                $this->clearLeadCache();
                $this->syncCallSession($lead->fresh(), $request, 'missed', 'no_answer');
                Log::info("Lead {$lead->id}: Video call ringing timed out (>35s). Auto-set to 'missed'.");
            }
        }

        // If no lead found or call is NOT actively ringing (e.g. ended, in_progress, missed, declined, idle)
        if (!$lead || $lead->video_call_status !== 'ringing') {
            return response()->json([
                'success'    => true,
                'isRinging'  => false,
                'is_ringing' => false,
                'leadId'     => $lead ? $lead->id : null,
                'lead_id'    => $lead ? $lead->id : null,
                'status'     => $lead ? ($lead->video_call_status ?: 'idle') : 'idle',
                'roomId'     => $lead ? $lead->video_call_room_id : null,
                'room_id'    => $lead ? $lead->video_call_room_id : null,
                'message'    => 'No active ringing call session found',
            ]);
        }

        Log::info("Lead {$lead->id}: verifyCustomerCall returning isRinging=true for Room {$lead->video_call_room_id}.");

        return response()->json([
            'success'            => true,
            'isRinging'          => true,
            'is_ringing'         => true,
            'leadId'             => $lead->id,
            'lead_id'            => $lead->id,
            'clientName'         => $lead->video_call_caller_name ?: ($lead->name ?: 'Admin Office'),
            'client_name'        => $lead->video_call_caller_name ?: ($lead->name ?: 'Admin Office'),
            'callerId'           => $lead->video_call_caller_id,
            'caller_id'          => $lead->video_call_caller_id,
            'callerName'         => $lead->video_call_caller_name ?: 'Admin Office',
            'caller_name'        => $lead->video_call_caller_name ?: 'Admin Office',
            'callerRole'         => $lead->video_call_caller_role ?: 'admin',
            'caller_role'        => $lead->video_call_caller_role ?: 'admin',
            'isVideo'            => (bool) $lead->video_call_is_video,
            'is_video'           => (bool) $lead->video_call_is_video,
            'roomId'             => $lead->video_call_room_id,
            'room_id'            => $lead->video_call_room_id,
            'status'             => 'ringing',
            'zegoAppId'          => (int) config('services.zego.app_id'),
            'zego_app_id'        => (int) config('services.zego.app_id'),
            'zegoAppSign'        => config('services.zego.app_sign'),
            'zego_app_sign'      => config('services.zego.app_sign'),
            'zegoServerSecret'   => config('services.zego.server_secret'),
            'zego_server_secret' => config('services.zego.server_secret'),
        ]);
    }

    /**
     * Mobile App API: Client/Surveyor responds to call pickup option (answer -> in_progress, decline -> declined, missed -> missed, end -> ended).
     * POST /api/customer/video-call/respond
     */
    public function customerRespondCall(Request $request): JsonResponse
    {
        $leadId = $request->input('lead_id') ?: ($request->input('leadId') ?: $request->input('id'));
        $action = $request->input('action');

        if (!$leadId) {
            return response()->json(['error' => 'lead_id or leadId is required', 'success' => false], 422);
        }

        if (!$action || !in_array($action, ['answer', 'decline', 'missed', 'end', 'cancel'])) {
            return response()->json(['error' => 'Valid action (answer, decline, missed, end, cancel) is required', 'success' => false], 422);
        }

        $lead = Lead::find($leadId);
        if (!$lead) {
            $numeric = preg_replace('/[^0-9]/', '', $leadId);
            if ($numeric) {
                $lead = Lead::find($numeric);
            }
        }

        if (!$lead) {
            return response()->json(['error' => 'Lead not found', 'success' => false], 404);
        }

        $action = $request->input('action');
        $status = match ($action) {
            'answer'            => 'in_progress',
            'decline'           => 'declined',
            'missed'            => 'missed',
            'end', 'cancel'     => 'ended',
            default             => 'ended',
        };

        $updates = ['video_call_status' => $status];
        if (in_array($status, ['ended', 'missed', 'declined', 'idle'])) {
            $updates['video_call_ended_at'] = now();
        }

        $lead->update($updates);
        $this->syncCallSession($lead->fresh(), $request, $status, $status === 'declined' ? 'declined' : ($status === 'ended' ? 'hangup' : null));
        $this->clearLeadCache();

        Log::info("Lead {$lead->id}: Mobile app user responded to video call with action '{$action}' -> status '{$status}'.");

        return response()->json([
            'success'   => true,
            'isRinging' => false,
            'status'    => $status,
            'roomId'    => $lead->video_call_room_id,
            'message'   => 'Call response recorded successfully',
        ]);
    }

    /**
     * Admin dispatches/edits mobile app user credentials email to customer.
     * POST /api/leads/{id}/send-app-credentials
     */
    public function sendAppCredentials(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $password = $request->input('password') ?: ($lead->app_user_password ?: 'NG-' . rand(100000, 999999));
        $customNote = $request->input('message', 'Please install the Next Gen app and login with these credentials.');

        if ($lead->email) {
            // Update or create User account in users table
            $user = \App\Models\User::where('email', strtolower(trim($lead->email)))->first();
            if (!$user) {
                $user = \App\Models\User::create([
                    'name'        => $lead->name ?: 'Customer',
                    'email'       => strtolower(trim($lead->email)),
                    'password'    => \Illuminate\Support\Facades\Hash::make($password),
                    'role'        => 'staff',
                    'permissions' => ['leads' => true, 'calendar' => true, 'contacts' => true],
                ]);
            } else {
                $user->update(['password' => \Illuminate\Support\Facades\Hash::make($password)]);
            }

            // Send Brevo SMTP email using luxury master layout
            $subject = "Your Next Gen Mobile App Login Credentials";
            $bodyHtml = "
                <div class=\"category-badge\">MOBILE APP ACCESS CREDENTIALS</div>
                <div class=\"headline\">Welcome to Next Gen Relocation</div>
                <div class=\"salutation\">
                    Hello <strong>" . e($lead->name ?: 'Valued Client') . "</strong>,<br/><br/>
                    " . e($customNote) . "
                </div>
                <div class=\"details-card\">
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">USERNAME / EMAIL</div>
                        <div class=\"detail-value\">" . e($lead->email) . "</div>
                    </div>
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">SECURE PASSWORD</div>
                        <div class=\"detail-value\" style=\"font-family: monospace; font-size: 15px; color: #C9A84C;\">" . e($password) . "</div>
                    </div>
                    <div class=\"detail-row\">
                        <div class=\"detail-label\">API SERVER URL</div>
                        <div class=\"detail-value\" style=\"font-family: monospace; font-size: 11px;\">" . e(config('app.url')) . "</div>
                    </div>
                </div>
                <div class=\"note-box\">
                    <p class=\"note-text\">Please keep these credentials secure. You can log into the Next Gen Mobile App to view survey schedules, track driver progress, and upload room videos.</p>
                </div>
            ";

            try {
                \Illuminate\Support\Facades\Mail::to($lead->email)->queue(
                    new \App\Mail\RawCustomEmail($subject, $bodyHtml)
                );

                \App\Models\Email::create([
                    'message_id'   => 'brevo-' . uniqid(),
                    'direction'    => 'outbound',
                    'from_email'   => config('mail.from.address', 'info@nextgenrelocation.co.uk'),
                    'from_name'    => config('mail.from.name', 'Next Gen Relocation'),
                    'to_email'     => $lead->email,
                    'subject'      => $subject,
                    'body_preview' => $customNote,
                    'body_html'    => $bodyHtml,
                    'is_read'      => true,
                    'received_at'  => now(),
                    'lead_id'      => $lead->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Failed to send mobile app credentials email to {$lead->email}: " . $e->getMessage());
            }
        }

        $lead->update([
            'app_user_password'   => $password,
            'credentials_sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Mobile app credentials sent successfully!',
            'lead'    => $this->present($lead->fresh()),
        ]);
    }

    /**
     * Get surveyor duties for surveyor app.
     * GET /api/surveyor/duties
     */
    public function surveyorDuties(Request $request): JsonResponse
    {
        $email = $request->query('email') ?: ($request->user()?->email);

        if (!$email) {
            return response()->json(['error' => 'Surveyor email is required.'], 400);
        }

        $leads = Lead::where(function($q) use ($email) {
            $q->where('surveyor_email', $email)
              ->orWhere('surveyor_name', $email);
        })
        ->orderBy('survey_requested_date')
        ->get();

        // Human-readable labels for the 3 survey types offered:
        //   physical → Surveyor visits the property in person
        //   virtual  → Live video call walkthrough (real-time)
        //   video    → Client uploads a pre-recorded walkthrough video
        $surveyTypeLabels = [
            'physical' => 'Physical Survey — In-person property visit',
            'virtual'  => 'Virtual Survey — Live video call walkthrough',
            'video'    => 'Video Upload Survey — Client-recorded walkthrough video',
        ];

        $duties = $leads->map(function($l) use ($surveyTypeLabels) {
            // Find linked job event (calendar event)
            $event = \App\Models\JobEvent::where('lead_id', $l->id)
                ->where('type', 'Survey')
                ->first();

            // Determine the survey duty status from actual DB fields
            $surveyStatus = 'assigned'; // default once surveyor is attached
            if ($l->surveyor_completed_at) {
                $surveyStatus = 'completed';
            } elseif ($l->survey_status === 'in_progress') {
                $surveyStatus = 'in_progress';
            } elseif ($l->survey_status === 'approved') {
                $surveyStatus = 'approved';
            } elseif ($l->survey_status === 'pending') {
                $surveyStatus = 'pending';
            } elseif ($l->survey_status === 'scheduled') {
                $surveyStatus = 'scheduled';
            }

            // Fetch the client's uploaded video for "video" type surveys
            $videoMedia = null;
            if ($l->survey_type === 'video') {
                $videoMedia = \App\Models\SurveyMedia::where('lead_id', $l->id)
                    ->where('type', 'video')
                    ->latest()
                    ->first();
            }

            $videoUrl = $videoMedia ? (str_starts_with($videoMedia->file_url, 'http') ? $videoMedia->file_url : url($videoMedia->file_url)) : null;

            return [
                // ── Lead Identification ──
                'id'                  => $l->id,
                'leadStatus'          => $l->status,
                'source'              => $l->source,

                // ── Client Details ──
                'clientName'          => $l->name,
                'clientEmail'         => $l->email,
                'clientPhone'         => $l->phone ?: null,

                // ── Move Information ──
                'fromLocation'        => $l->from_location ?: null,
                'toLocation'          => $l->to_location ?: null,
                'moveType'            => $l->move_type ?: null,
                'leadType'            => $l->lead_type ?: 'domestic',
                'moveDate'            => $l->move_date?->format('Y-m-d'),
                'estimatedValue'      => $l->est_value ? (float) $l->est_value : null,

                // ── Survey Schedule ──
                'surveyDate'          => $l->survey_requested_date ?: ($l->survey_booked_at ? $l->survey_booked_at->format('Y-m-d') : null),
                'surveyTime'          => $l->survey_requested_time_range ?: null,

                // ── Survey Type (3 types offered) ──
                // Values: "physical" | "virtual" | "video"
                //   physical = Surveyor visits the client's property in person
                //   virtual  = Live video call walkthrough (real-time)
                //   video    = Client uploads a pre-recorded walkthrough video
                'surveyType'          => $l->survey_type ?: null,
                'surveyTypeLabel'     => $surveyTypeLabels[$l->survey_type] ?? null,

                // ── Survey Status ──
                // Values: "pending" | "scheduled" | "approved" | "assigned" | "in_progress" | "completed"
                'surveyStatus'        => $surveyStatus,
                'surveyApprovedAt'    => $this->formatIso($l->survey_approved_at),
                'completedAt'         => $this->formatIso($l->surveyor_completed_at),

                // ── Video Info (Virtual & Video Upload) ──
                'videoUrl'            => $videoUrl,
                'videoCallRoomId'     => $l->video_call_room_id ?: null,
                'videoCallStatus'     => $l->video_call_status ?: null,
                'videoCallToken'      => $l->video_call_token ?: null,

                // ── Client Notes & Survey Report ──
                'clientNotes'         => $l->survey_notes ?: null,
                'reportNotes'         => $l->surveyor_report_notes ?: null,
                'media'               => is_array($l->surveyor_media) ? $l->surveyor_media : json_decode($l->surveyor_media ?? '[]', true),

                // ── Calendar Event ──
                'calendarEvent'       => $event ? [
                    'id'    => $event->id,
                    'title' => $event->title,
                    'date'  => $event->date,
                    'time'  => $event->time,
                ] : null,
            ];
        });

        return response()->json([
            'surveyorEmail' => $email,
            'totalDuties'   => $duties->count(),
            'surveyTypes'   => [
                'physical' => 'Physical Survey — In-person property visit',
                'virtual'  => 'Virtual Survey — Live video call walkthrough',
                'video'    => 'Video Upload Survey — Client-recorded walkthrough video',
            ],
            'duties'        => $duties,
        ]);
    }

    /**
     * Surveyor starts the survey (Flutter App).
     * POST /api/surveyor/duties/{id}/start
     */
    public function startSurveyDuty(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $lead->update(['survey_status' => 'in_progress']);
        
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'info',
            'title'   => 'Survey Started 🚀',
            'message' => "Surveyor {$lead->surveyor_name} started survey for {$lead->name}.",
            'link'    => '/app/surveys?view=' . $lead->id,
            'is_read' => false,
        ]);
        
        $this->clearLeadCache();

        return response()->json([
            'message' => 'Survey started successfully',
            'status'  => 'in_progress'
        ]);
    }

    /**
     * Generate and download client relocation file PDF summary.
     * GET /api/leads/{id}/pdf
     */
    public function downloadPdf(string $id)
    {
        set_time_limit(120); // Increase time limit for PDF generation
        
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        // Fetch related data
        $quotation = \App\Models\Quotation::where('lead_id', $lead->id)->orderBy('created_at', 'desc')->first();
        $surveyReminders = \App\Models\SurveyReminder::where('lead_id', $lead->id)->orderBy('scheduled_at')->get();
        $quotationReminders = \App\Models\QuotationReminder::where('lead_id', $lead->id)->orderBy('scheduled_at')->get();

        // Build HTML template for PDF
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset=\"utf-8\">
            <title>Relocation Plan & Quotation — " . e($lead->name) . "</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1F2937; margin: 0; padding: 15px; font-size: 12px; line-height: 1.4; }
                .header { border-bottom: 2px solid #C9A84C; padding-bottom: 12px; margin-bottom: 20px; }
                .header table { width: 100%; }
                .logo { font-size: 20px; font-weight: bold; color: #000000; letter-spacing: 1px; }
                .meta-info { text-align: right; color: #4B5563; font-size: 10px; line-height: 1.3; }
                .section-title { font-size: 13px; font-weight: bold; color: #C9A84C; text-transform: uppercase; border-bottom: 1px solid #E5E7EB; padding-bottom: 4px; margin-top: 20px; margin-bottom: 10px; }
                .details-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                .details-grid td { padding: 4px 6px; vertical-align: top; }
                .label { font-weight: bold; color: #4B5563; width: 28%; font-size: 10px; text-transform: uppercase; }
                .value { color: #111827; }
                .timeline-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
                .timeline-table th { background-color: #F9FAFB; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; color: #4B5563; border-bottom: 1px solid #E5E7EB; }
                .timeline-table td { padding: 6px 8px; border-bottom: 1px solid #F3F4F6; font-size: 11px; }
                .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
                .badge-pending { background-color: #FEF3C7; color: #92400E; }
                .badge-sent { background-color: #D1FAE5; color: #065F46; }
                .badge-skipped { background-color: #E5E7EB; color: #374151; }
                .badge-stopped { background-color: #FEE2E2; color: #991B1B; }
                .notes-box { background-color: #F9FAFB; border-left: 3px solid #C9A84C; padding: 10px; font-style: italic; margin-top: 8px; color: #4B5563; font-size: 11px; }
                .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; color: #9CA3AF; font-size: 9px; border-top: 1px solid #E5E7EB; padding-top: 8px; }
            </style>
        </head>
        <body>
            <div class=\"header\">
                <table>
                    <tr>
                        <td>
                            <div class=\"logo\">NEXT GEN RELOCATION</div>
                            <div style=\"font-size: 10px; color: #6B7280;\">Your Comprehensive Relocation Plan</div>
                        </td>
                        <td class=\"meta-info\">
                            <strong>FILE REFERENCE:</strong> " . e($lead->id) . "<br/>
                            <strong>DATE GENERATED:</strong> " . now()->format('d M Y H:i') . "<br/>
                            <strong>STATUS:</strong> " . strtoupper(e($lead->status)) . "
                        </td>
                    </tr>
                </table>
            </div>

            <div class=\"section-title\">1. Client Profile & Move Parameters</div>
            <table class=\"details-grid\">
                <tr>
                    <td class=\"label\">Client Name:</td>
                    <td class=\"value\">" . e($lead->name) . "</td>
                    <td class=\"label\">Move Date:</td>
                    <td class=\"value\">" . e($lead->move_date ?: 'TBD') . "</td>
                </tr>
                <tr>
                    <td class=\"label\">Email Address:</td>
                    <td class=\"value\">" . e($lead->email) . "</td>
                    <td class=\"label\">Move Type:</td>
                    <td class=\"value\">" . e($lead->move_type) . "</td>
                </tr>
                <tr>
                    <td class=\"label\">Phone Number:</td>
                    <td class=\"value\">" . e($lead->phone ?: 'N/A') . "</td>
                    <td class=\"label\">Relocation Route:</td>
                    <td class=\"value\">" . e($lead->from_location) . " &rarr; " . e($lead->to_location) . "</td>
                </tr>
            </table>

            <div class=\"section-title\">2. Pre-Move Survey Details</div>
            <table class=\"details-grid\">
                <tr>
                    <td class=\"label\">Survey Status:</td>
                    <td class=\"value\">" . ($lead->survey_approved_at ? 'Approved / Completed' : 'Pending Action') . "</td>
                    <td class=\"label\">Survey Mode:</td>
                    <td class=\"value\" style=\"text-transform: capitalize;\">" . e($lead->survey_type ?: 'physical') . " Survey</td>
                </tr>
                <tr>
                    <td class=\"label\">Assigned Surveyor:</td>
                    <td class=\"value\">" . e($lead->surveyor_name ?: 'None Assigned') . " (" . e($lead->surveyor_email ?: 'N/A') . ")</td>
                    <td class=\"label\">Scheduled Slot:</td>
                    <td class=\"value\">" . e($lead->survey_requested_date ?: 'TBD') . " at " . e($lead->survey_requested_time_range ?: '10:00 AM') . "</td>
                </tr>
            </table>";
        if ($lead->surveyor_report_notes) {
            // Clean up the raw text for the client
            $cleanNotes = preg_replace('/={10,}/', '<hr style="border:0; border-top:1px solid #E5E7EB; margin:10px 0;">', $lead->surveyor_report_notes);
            
            $html .= "
            <div class=\"section-title\">3. Inspection Findings & Plan</div>
            <div class=\"notes-box\">" . nl2br($cleanNotes) . "</div>";
        }

        $html .= "
            <div class=\"section-title\">4. Financial Quotation</div>";

        if ($quotation) {
            $html .= "
            <table class=\"details-grid\">
                <tr>
                    <td class=\"label\">Quotation Reference:</td>
                    <td class=\"value\">" . e($quotation->quote_number) . "</td>
                    <td class=\"label\">Final Amount:</td>
                    <td class=\"value\" style=\"font-weight: bold; color: #C9A84C; font-size: 13px;\">&pound;" . number_format($quotation->price, 2) . "</td>
                </tr>
                <tr>
                    <td class=\"label\">Approval Status:</td>
                    <td class=\"value\">" . ($quotation->approved_at ? 'Approved on ' . $quotation->approved_at->format('d M Y H:i') : 'Awaiting Approval') . "</td>
                    <td class=\"label\">Draft Prepared:</td>
                    <td class=\"value\">" . $quotation->created_at->format('d M Y H:i') . "</td>
                </tr>
            </table>";

            if ($quotation->notes) {
                $html .= "
                <div style=\"font-size: 10px; font-weight: bold; color: #4B5563; margin-top: 5px;\">QUOTATION BREAKDOWN DETAILS:</div>
                <div class=\"notes-box\">" . nl2br(e($quotation->notes)) . "</div>";
            }
        } else {
            $html .= "
            <div style=\"font-style: italic; color: #6B7280; padding: 10px;\">Quotation is currently being prepared and will be sent shortly.</div>";
        }

        $html .= "
            <div style=\"margin-top: 30px; text-align: center; font-size: 10px; color: #6B7280; border-top: 1px solid #E5E7EB; padding-top: 15px;\">
                <strong>Next Gen Relocation</strong><br/>
                Thank you for choosing us for your relocation needs. If you have any questions, please contact our support team.
            </div>
            
            <div class=\"footer\">
                Page 1 of 1 &nbsp;&middot;&nbsp; Generated securely by Next Gen CRM
            </div>
        </body>
        </html>";

        // Generate PDF using DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        return $pdf->download('Relocation_Plan_' . $lead->id . '.pdf');
    }

    /**
     * Build clean, structured, highly-readable survey report notes.
     */
    private function buildStructuredReportNotes(Lead $lead, Request $request, ?string $cleanFindings, ?string $rawReportNotes): string
    {
        $propType     = $request->input('property_type') ?: ($lead->move_type ?: 'Domestic');
        $volume       = $request->input('cargo_volume') ?: 'Not specified';
        $boxes        = $request->input('total_boxes') ?: 'Not specified';
        $vehicle      = $request->input('recommended_vehicle') ?: 'TBD';
        $packing      = $request->input('packing_service') ?: $request->input('packing_type') ?: 'Standard';
        $accessOrigin = $request->input('access_origin') ?: $request->input('floors_origin') ?: 'Ground Floor';
        
        $lift         = $request->input('lift_available') ?? $request->input('lift_origin');
        $liftText     = $lift === true ? 'Available' : ($lift === false ? 'Not Available' : 'N/A');
        
        $parking      = $request->input('parking_available');
        $parkingText  = $parking === true ? 'Available' : ($parking === false ? 'Not Available' : 'N/A');

        // Dismantling items
        $dismantleReq = $request->input('requires_disassembly');
        $beds         = (int) $request->input('beds_quantity');
        $wardrobes    = (int) $request->input('wardrobes_quantity');
        $dismantleRaw = $request->input('custom_dismantle_items') ?: $request->input('items');
        if (is_string($dismantleRaw)) $dismantleRaw = json_decode($dismantleRaw, true);
        
        $dismantleParts = [];
        if ($beds > 0) $dismantleParts[] = "Beds: {$beds}";
        if ($wardrobes > 0) $dismantleParts[] = "Wardrobes: {$wardrobes}";
        if (is_array($dismantleRaw)) {
            foreach ($dismantleRaw as $it) {
                if (is_array($it) && !empty($it['name'])) {
                    $qty = $it['quantity'] ?? $it['qty'] ?? 1;
                    $dismantleParts[] = "{$it['name']}: {$qty}";
                }
            }
        }
        $dismantleText = ($dismantleReq || !empty($dismantleParts)) 
            ? "YES (" . implode(', ', $dismantleParts) . ")" 
            : "NO";

        // Fragile items
        $fragileReq  = $request->input('has_fragile_items');
        $fineArt     = $request->input('fine_art_paintings');
        $piano       = $request->input('piano_antique_items');
        $fragileRaw  = $request->input('custom_fragile_fields');
        if (is_string($fragileRaw)) $fragileRaw = json_decode($fragileRaw, true);

        $fragileParts = [];
        if ($fineArt) $fragileParts[] = "Fine Art / Paintings";
        if ($piano)   $fragileParts[] = "Piano / Antiques";
        if (is_array($fragileRaw)) {
            foreach ($fragileRaw as $f) {
                if (is_array($f) && !empty($f['label'])) {
                    $fragileParts[] = $f['label'];
                }
            }
        }
        $fragileText = ($fragileReq || !empty($fragileParts))
            ? "YES (" . implode(', ', $fragileParts) . ")"
            : "NO";

        $instructions = trim($request->input('special_instructions') ?: '');

        $route = ($lead->from_location && $lead->to_location)
            ? "{$lead->from_location} → {$lead->to_location}"
            : ($lead->from_location ?: 'TBD');

        $out = [];
        $out[] = "==================================================";
        $out[] = "HOUSE RELOCATION & LUGGAGE SURVEY REPORT";
        $out[] = "==================================================";
        $out[] = "Lead ID: {$lead->id}";
        $out[] = "Client Name: {$lead->name}";
        $out[] = "Client Phone: " . ($lead->phone ?: 'N/A');
        $out[] = "Route: {$route}";
        $out[] = "Move Category: {$propType}";
        $out[] = "Estimated Cargo Volume: {$volume}";
        $out[] = "Number of Boxes / Cartons: {$boxes}";
        $out[] = "Recommended Vehicle: {$vehicle}";
        $out[] = "Packing Service: {$packing}";
        $out[] = "Origin Access: {$accessOrigin}";
        $out[] = "Lift Available: {$liftText}";
        $out[] = "Parking Available: {$parkingText}";
        $out[] = "";
        $out[] = "DISMANTLING & REASSEMBLY REQUIRED:";
        $out[] = $dismantleText;
        $out[] = "";
        $out[] = "FRAGILE / HIGH-VALUE ITEMS:";
        $out[] = $fragileText;

        if ($instructions !== '') {
            $out[] = "";
            $out[] = "CUSTOMER SPECIAL INSTRUCTIONS:";
            $out[] = $instructions;
        }

        if ($cleanFindings !== null && $cleanFindings !== '') {
            $out[] = "";
            $out[] = "SURVEYOR INSPECTION FINDINGS & ROOM INVENTORY:";
            $out[] = $cleanFindings;
        }

        $out[] = "==================================================";

        return implode("\n", $out);
    }
}
