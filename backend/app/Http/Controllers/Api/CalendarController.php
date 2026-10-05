<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = JobEvent::select(['id', 'title', 'type', 'status', 'event_date', 'event_time', 'driver', 'vehicle', 'location', 'lead_id']);

        if ($user && $user->role === 'surveyor') {
            $email = trim(strtolower($user->email));
            $name = trim(strtolower($user->name));
            $query->whereHas('lead', function($q) use ($email, $name) {
                $q->whereRaw('LOWER(surveyor_email) = ?', [$email])
                  ->orWhereRaw('LOWER(surveyor_name) = ?', [$email])
                  ->orWhereRaw('LOWER(surveyor_name) = ?', [$name]);
            });
        }

        $events = $query->orderBy('event_date', 'asc')
            ->get()
            ->map(fn($e) => [
                'id'       => $e->id,
                'title'    => $e->title,
                'type'     => $e->type,
                'status'   => $e->status,
                'date'     => $e->event_date?->format('Y-m-d'),
                'time'     => $e->event_time,
                'driver'   => $e->driver,
                'vehicle'  => $e->vehicle,
                'location' => $e->location,
                'leadId'   => $e->lead_id,
            ])
            ->toArray();

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $newId  = 'J-' . mt_rand(100000, 99999999);
        $leadId = $request->input('lead_id') ?: $request->input('leadId');

        $clientName  = $request->input('client_name') ?: $request->input('clientName');
        $clientEmail = $request->input('client_email') ?: $request->input('clientEmail');
        $clientPhone = $request->input('client_phone') ?: $request->input('clientPhone');
        $moveType    = $request->input('move_type') ?: ($request->input('moveType') ?: 'House Move');
        $fromLoc     = $request->input('from_location') ?: ($request->input('from') ?: $request->input('location'));
        $toLoc       = $request->input('to_location') ?: $request->input('to');
        $estValue    = (float) ($request->input('est_value') ?: ($request->input('estValue') ?: 0));
        $notes       = $request->input('notes');

        // Auto-create or link Lead if client details are provided
        if (!$leadId && ($clientName || $clientEmail)) {
            $leadId = 'L-' . mt_rand(100000, 99999999);
            try {
                $lead = \App\Models\Lead::create([
                    'id'            => $leadId,
                    'name'          => $clientName ?: 'Calendar Lead',
                    'email'         => $clientEmail,
                    'phone'         => $clientPhone,
                    'source'        => 'Calendar Direct Schedule',
                    'status'        => $request->input('type') === 'Survey' ? 'survey-booked' : 'new',
                    'stage'         => $request->input('type') === 'Survey' ? 'Survey' : 'New',
                    'move_type'     => $moveType,
                    'from_location' => $fromLoc ?: 'TBD',
                    'to_location'   => $toLoc ?: 'TBD',
                    'move_date'     => $request->input('date', now()->format('Y-m-d')),
                    'est_value'     => $estValue,
                    'survey_notes'  => $notes,
                ]);

                // Create associated contact record
                if ($clientEmail) {
                    \App\Models\Contact::updateOrCreate(
                        ['email' => $clientEmail],
                        [
                            'id'             => 'C-' . mt_rand(100000, 99999999),
                            'name'           => $clientName,
                            'email'          => $clientEmail,
                            'phone'          => $clientPhone,
                            'type'           => 'Customer',
                            'lifetime_value' => $estValue ?: 1500,
                            'status'         => 'Active',
                        ]
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("CalendarController store: Failed to auto-create lead: " . $e->getMessage());
            }
        }

        $title = $request->input('title');
        if (!$title) {
            $title = $clientName ? "{$clientName} — {$moveType}" : 'Scheduled Event';
        }

        $locationStr = $request->input('location');
        if (!$locationStr && ($fromLoc || $toLoc)) {
            $locationStr = trim("{$fromLoc} → {$toLoc}", ' →');
        }

        $event = JobEvent::create([
            'id'         => $newId,
            'title'      => $title,
            'type'       => $request->input('type', 'Job'),
            'status'     => $request->input('status', 'booked'),
            'event_date' => $request->input('date', now()->format('Y-m-d')),
            'event_time' => $request->input('time', '09:00 AM'),
            'lead_id'    => $leadId,
            'driver'     => $request->input('driver', 'Unassigned'),
            'vehicle'    => $request->input('vehicle', '3.5t Luton Van'),
            'location'   => $locationStr ?: 'Main Office',
            'notes'      => $notes,
        ]);

        try {
            
        } catch (\Throwable $ex) {
            // ignore
        }

        return response()->json([
            'id'       => $event->id,
            'title'    => $event->title,
            'type'     => $event->type,
            'status'   => $event->status,
            'date'     => $event->event_date?->format('Y-m-d'),
            'time'     => $event->event_time,
            'leadId'   => $event->lead_id,
            'driver'   => $event->driver,
            'vehicle'  => $event->vehicle,
            'location' => $event->location,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $event = JobEvent::find($id);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $newDate = $request->input('date', $event->event_date?->format('Y-m-d'));
        $newTime = $request->input('time', $event->event_time);

        $event->update([
            'event_date' => $newDate,
            'event_time' => $newTime,
            'driver'     => $request->input('driver', $event->driver),
        ]);

        // Write the new date/time back onto the linked lead's lifecycle fields
        // (spec #8: editing date & time from the calendar updates the schedule).
        if ($event->lead_id) {
            $lead = \App\Models\Lead::find($event->lead_id);
            if ($lead) {
                if ($event->type === 'Survey') {
                    // Survey events are keyed by (lead_id, type 'Survey').
                    $lead->update([
                        'survey_requested_date'      => $newDate,
                        'survey_requested_time_range' => $newTime,
                        'survey_booked_at'           => "{$newDate} {$newTime}:00",
                    ]);

                    // Send email to Client
                    if ($lead->email) {
                        $clientSubject = "Your Pre-move Survey Has Been Rescheduled";
                        $clientBody = "
                            <div class=\"category-badge\">SURVEY SCHEDULE UPDATED</div>
                            <div class=\"headline\">Your Survey Has Been Rescheduled</div>
                            <div class=\"salutation\">
                                Hello <strong>" . e($lead->name) . "</strong>,<br/><br/>
                                Please be informed that the admin has rescheduled your pre-move survey schedule to the following time slot.
                            </div>
                            <div class=\"details-card\">
                                <div class=\"detail-row\">
                                    <div class=\"detail-label\">NEW SURVEY DATE & TIME</div>
                                    <div class=\"detail-value\" style=\"color: #C9A84C; font-weight: bold;\">" . e($newDate) . " at " . e($newTime) . "</div>
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
                                <p class=\"note-text\">If you have any questions or need to make further adjustments, please feel free to reach out to us.</p>
                            </div>
                        ";

                        try {
                            \Illuminate\Support\Facades\Mail::to($lead->email)->queue(
                                new \App\Mail\RawCustomEmail($clientSubject, $clientBody)
                            );
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Failed to send reschedule email to client {$lead->email}: " . $e->getMessage());
                        }
                    }

                    // Send email to Surveyor
                    if ($lead->surveyor_email) {
                        $surveyorSubject = "Survey Rescheduled Notification: " . ($lead->name ?: 'Client');
                        $surveyorBody = "
                            <div class=\"category-badge\">JOB SCHEDULE UPDATED</div>
                            <div class=\"headline\">Survey Job Rescheduled</div>
                            <div class=\"salutation\">
                                Hello <strong>" . e($lead->surveyor_name ?: 'Surveyor') . "</strong>,<br/><br/>
                                Please note that the admin has updated the schedule for your assigned survey client job.
                            </div>
                            <div class=\"details-card\">
                                <div class=\"detail-row\">
                                    <div class=\"detail-label\">CLIENT NAME</div>
                                    <div class=\"detail-value\">" . e($lead->name) . "</div>
                                </div>
                                <div class=\"detail-row\">
                                    <div class=\"detail-label\">NEW SCHEDULED SLOT</div>
                                    <div class=\"detail-value\" style=\"color: #C9A84C; font-weight: bold;\">" . e($newDate) . " at " . e($newTime) . "</div>
                                </div>
                                <div class=\"detail-row\">
                                    <div class=\"detail-label\">MOVE ROUTE</div>
                                    <div class=\"detail-value\">" . e($lead->from_location ?: '—') . " → " . e($lead->to_location ?: '—') . "</div>
                                </div>
                            </div>
                            <div class=\"note-box\">
                                <p class=\"note-text\">This update is reflected in your workspace, duties list, and Surveyor Mobile App calendar.</p>
                            </div>
                        ";

                        try {
                            \Illuminate\Support\Facades\Mail::to($lead->surveyor_email)->queue(
                                new \App\Mail\RawCustomEmail($surveyorSubject, $surveyorBody)
                            );
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Failed to send reschedule email to surveyor {$lead->surveyor_email}: " . $e->getMessage());
                        }
                    }

                } elseif ($event->type === 'Reminder' && $lead->reminder_next_at) {
                    // A single reminder marker tracks the next due reminder.
                    $lead->update(['reminder_next_at' => "{$newDate} {$newTime}:00"]);
                } elseif ($event->type === 'Job') {
                    // Rescheduling the move updates the lead's move date (spec #8).
                    $lead->update(['move_date' => $newDate]);
                }
            }
        }

        try {
            
        } catch (\Throwable $ex) {
            // ignore
        }

        return response()->json([
            'message' => 'Event rescheduled successfully',
            'id'      => $event->id,
            'date'    => $newDate,
            'time'    => $newTime,
        ]);
    }
}
