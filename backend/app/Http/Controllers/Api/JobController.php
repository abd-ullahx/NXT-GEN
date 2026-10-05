<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobEvent;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jobs module (spec #10).
 *
 * A "job" is the move itself — represented by the JobEvent of type 'Job'.
 * Jobs flow through statuses:
 *   booked            → awaiting a driver / not yet done
 *   completed_won     → move completed successfully
 *   not_completed     → move failed / needs a driver reassigned
 *
 * The Jobs page groups by status; the admin can assign/reassign a driver and
 * flip the status. Every status change is mirrored onto the linked lead so the
 * lead's track-status stepper stays in sync.
 */
class JobController extends Controller
{
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

    private function present(JobEvent $j): array
    {
        $lead = $j->relationLoaded('lead') ? $j->lead : ($j->lead_id ? Lead::find($j->lead_id) : null);
        $clientStatus = $j->client_job_status ?: ($lead?->client_job_status ?: null);
        $driverStatus = $j->driver_job_status ?: ($lead?->driver_job_status ?: null);
        $bothConfirmed = ($clientStatus === 'confirmed') && ($driverStatus === 'confirmed');

        $rawDriver = $j->driver ?: ($lead?->driver_name ?: null);
        $driverName = ($rawDriver && !in_array($rawDriver, ['Unassigned', 'Unassigned Driver'], true)) ? $rawDriver : 'Unassigned';

        return [
            'id'                  => $j->id,
            'title'               => $j->title,
            'type'                => $j->type,
            'status'              => $j->status ?: 'booked',
            'date'                => $j->event_date?->format('Y-m-d'),
            'time'                => $j->event_time,
            'leadId'              => $j->lead_id,
            'lead_id'             => $j->lead_id,
            'client_name'         => $lead?->name ?: 'Valued Client',
            'client_phone'        => $lead?->phone ?: 'N/A',
            'client_email'        => $lead?->email ?: 'N/A',
            'driver'              => $driverName,
            'driver_name'         => $driverName,
            'vehicle'             => $j->vehicle ?: 'Unassigned',
            'vehicle_type'        => $j->vehicle ?: ($lead?->recommended_vehicle ?: 'Removal Van'),
            'location'            => $j->location ?: 'N/A',
            'pickup_address'      => $lead?->from_location ?: 'N/A',
            'destination_address' => $lead?->to_location ?: 'N/A',
            'scheduled_datetime'  => ($j->event_date?->format('Y-m-d') ?: date('Y-m-d')) . ' ' . ($j->event_time ?: '09:00 AM'),
            'started_at'          => $this->formatIso($j->started_at) ?: ($this->formatIso($lead?->job_started_at) ?: null),
            'completed_at'        => $this->formatIso($j->completed_at) ?: ($this->formatIso($lead?->job_completed_at) ?: null),
            'start_odometer'      => (float)($j->start_odometer ?: ($lead?->start_odometer ?: 0)),
            'end_odometer'        => (float)($j->end_odometer ?: ($lead?->end_odometer ?: 0)),
            'distance_km'         => (float)($j->distance_km ?: ($lead?->distance_km ?: 0)),
            'fuel_liters'         => (float)($j->fuel_liters ?: ($lead?->fuel_liters ?: 0)),
            'notes'               => $j->notes,
            'client'              => $lead?->name,
            'moveType'            => $lead?->move_type,
            'client_status'       => $clientStatus,
            'driver_status'       => $driverStatus,
            'is_confirmed'        => $bothConfirmed,
            'leadMoveDate'        => $lead?->move_date?->format('Y-m-d'),
            'proofs'              => [
                'selfie_url'      => $j->selfie_url ?: $lead?->selfie_url,
                'start_meter_url' => $j->start_meter_url ?: $lead?->start_meter_url,
                'start_back_url'  => $j->start_back_url ?: $lead?->start_back_url,
                'end_meter_url'   => $j->end_meter_url ?: $lead?->end_meter_url,
                'end_back_url'    => $j->end_back_url ?: $lead?->end_back_url,
            ],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validStatuses = ['booked', 'job-booked', 'assigned', 'in_progress', 'won', 'completed', 'not_completed', 'not-completed'];

        // Filter out jobs whose leads haven't reached the booked stage (no destructive delete)
        $query = JobEvent::query()->with('lead')->where('type', 'Job')
            ->where(function ($q) use ($validStatuses) {
                $q->whereNull('lead_id')
                  ->orWhereIn('lead_id', function ($sub) use ($validStatuses) {
                      $sub->select('id')->from('leads')->whereIn('status', $validStatuses);
                  });
            })
            ->orderBy('event_date', 'asc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Removed 1-hour cache — jobs must always be real-time
        $jobs = $query->get()->map(function ($j) {
            return $this->present($j);
        });

        $responsePayload = [
            'jobs'    => $jobs,
            'grouped' => [
                'booked'        => $jobs->whereIn('status', ['booked', 'assigned', 'in_progress'])->values(),
                'completed_won' => $jobs->where('status', 'completed_won')->values(),
                'not_completed' => $jobs->where('status', 'not_completed')->values(),
            ],
        ];
        return response()->json($responsePayload);
    }



    public function assignDriver(Request $request, string $id): JsonResponse
    {
        $job = JobEvent::find($id);
        if (!$job) {
            $job = JobEvent::where('lead_id', $id)->where('type', 'Job')->first();
        }
        if (!$job) {
            
            return response()->json(['error' => 'Job not found'], 404);
        }

        $driver = $request->input('driver', $job->driver);
        $vehicle = $request->input('vehicle', $job->vehicle);
        $eventDate = $request->input('event_date') ?: ($request->input('date') ?: ($job->event_date?->format('Y-m-d') ?: null));
        $eventTime = $request->input('event_time') ?: ($request->input('time') ?: ($job->event_time ?: '09:00 AM'));

        $assignmentType = $request->input('assignment_type', 'approval'); // 'direct' or 'approval'
        $isDirect = ($assignmentType === 'direct');

        $scheduleToken = $job->job_schedule_token ?: md5($job->id . '_' . time());
        $jobUpdates = [
            'driver'             => $driver,
            'vehicle'            => $vehicle,
            'job_schedule_token' => $scheduleToken,
            'client_job_status'  => $isDirect ? 'confirmed' : 'pending',
            'driver_job_status'  => $isDirect ? 'confirmed' : 'pending',
        ];
        if ($eventDate) {
            $jobUpdates['event_date'] = $eventDate;
        }
        if ($eventTime) {
            $jobUpdates['event_time'] = $eventTime;
        }
        $job->update($jobUpdates);

        $publicBase = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');
        $clientConfirmUrl = "{$publicBase}/job/schedule/confirm?token={$scheduleToken}&role=client";
        $clientRescheduleUrl = "{$publicBase}/job/schedule/reschedule?token={$scheduleToken}&role=client";
        $driverConfirmUrl = "{$publicBase}/job/schedule/confirm?token={$scheduleToken}&role=driver";
        $driverRescheduleUrl = "{$publicBase}/job/schedule/reschedule?token={$scheduleToken}&role=driver";

        if ($job->lead_id) {
            $lead = Lead::find($job->lead_id);
            if ($lead) {
                $leadUpdates = [
                    'driver_name'        => $driver,
                    'driver_assigned_at' => now(),
                    'status'             => 'assigned',
                    'stage'              => 'Job',
                    'job_schedule_token' => $scheduleToken,
                    'client_job_status'  => $isDirect ? 'confirmed' : 'pending',
                    'driver_job_status'  => $isDirect ? 'confirmed' : 'pending',
                ];
                if ($eventDate) {
                    $leadUpdates['move_date'] = $eventDate;
                }
                if ($eventTime) {
                    $leadUpdates['arrival_window'] = $eventTime;
                }
                $lead->update($leadUpdates);

                // 1. Send detailed email to Assigned Driver with Action Buttons
                $driverUser = \App\Models\User::where('name', $driver)->orWhere('id', $request->input('driverId'))->first();
                $driverEmail = $driverUser?->email ?: $lead->driver_email;

                if ($driverEmail) {
                    $subject = "🚚 New Job Assignment: Relocation Move #" . $lead->id;
                    $bodyHtml = "
                        <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #c9a84c;\">
                            <h2 style=\"color: #c9a84c; margin-top: 0;\">Next Gen Relocation — Driver Job Assignment</h2>
                            <p style=\"font-size: 14px;\">Hello <strong>" . e($driver) . "</strong>,</p>
                            <p style=\"font-size: 14px;\">You have been assigned a new relocation job. Below are the complete client and job details required for your assignment:</p>
                            
                            <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                                <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📋 Job & Client Overview</h3>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Job ID:</strong> " . e($job->id) . " (Lead #" . e($lead->id) . ")</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Name:</strong> " . e($lead->name) . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Phone:</strong> " . e($lead->phone ?: 'N/A') . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Email:</strong> " . e($lead->email ?: 'N/A') . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Move Type:</strong> " . e($lead->move_type ?: 'House Move') . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Assigned Vehicle:</strong> " . e($vehicle ?: 'Removal Truck') . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Move Date & Scheduled Arrival:</strong> " . e($eventDate ? \Carbon\Carbon::parse($eventDate)->format('d M Y') : 'Scheduled Date') . " at " . e($eventTime) . "</p>
                            </div>

                            <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                                <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📍 Locations & Route</h3>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Pickup Address:</strong> " . e($lead->from_location ?: 'TBD') . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Destination Address:</strong> " . e($lead->to_location ?: 'TBD') . "</p>
                                " . ($job->notes ? "<p style=\"margin: 6px 0; font-size: 13px;\"><strong>Job Notes:</strong> " . e($job->notes) . "</p>" : "") . "
                            </div>

                            <div style=\"margin: 24px 0; text-align: center;\">
                                <a href=\"{$driverConfirmUrl}\" style=\"background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 13px 24px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(16,185,129,0.3);\">
                                    ✅ Confirm & Agree to Schedule
                                </a>
                                <a href=\"{$driverRescheduleUrl}\" style=\"background: #1e2538; color: #f59e0b; border: 1px solid #f59e0b; text-decoration: none; padding: 12px 22px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; margin-left: 10px;\">
                                    📅 Request Reschedule
                                </a>
                            </div>

                            <p style=\"font-size: 13px; color: #94a3b8;\">Please click above to confirm your availability or request a schedule adjustment. Access your Driver App for live navigation details.</p>
                        </div>
                    ";

                    try {
                        \Illuminate\Support\Facades\Mail::to($driverEmail)->queue(
                            new \App\Mail\RawCustomEmail($subject, $bodyHtml)
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Failed to send driver assignment email to {$driverEmail}: " . $e->getMessage());
                    }
                }

                // 2. Send confirmation email to Customer about Driver arrival schedule with Action Buttons
                if ($lead->email) {
                    $clientSubject = "🚚 Your Relocation Team & Schedule Confirmed — Lead #" . $lead->id;
                    $formattedDate = $eventDate ? \Carbon\Carbon::parse($eventDate)->format('d M Y') : ($lead->move_date ? \Carbon\Carbon::parse($lead->move_date)->format('d M Y') : 'Scheduled Date');
                    $clientBodyHtml = "
                        <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #c9a84c;\">
                            <h2 style=\"color: #c9a84c; margin-top: 0;\">Next Gen Relocation — Service Confirmation</h2>
                            <p style=\"font-size: 14px;\">Dear <strong>" . e($lead->name) . "</strong>,</p>
                            <p style=\"font-size: 14px;\">Great news! Your relocation team and driver have been scheduled and assigned to your upcoming move.</p>
                            
                            <div style=\"background-color: #141722; border: 1px solid #c9a84c; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                                <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">🚚 Scheduled Arrival & Driver Details</h3>
                                <p style=\"margin: 6px 0; font-size: 14px; font-weight: bold; color: #f59e0b;\">📅 Relocation Date: " . e($formattedDate) . "</p>
                                <p style=\"margin: 6px 0; font-size: 14px; font-weight: bold; color: #10b981;\">⏰ Driver Arrival Time: " . e($eventTime) . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Assigned Lead Driver:</strong> " . e($driver) . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Allocated Vehicle:</strong> " . e($vehicle ?: 'Removal Van') . "</p>
                            </div>

                            <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                                <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📍 Address Overview</h3>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Pickup Address:</strong> " . e($lead->from_location ?: 'TBD') . "</p>
                                <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Destination Address:</strong> " . e($lead->to_location ?: 'TBD') . "</p>
                            </div>

                            <div style=\"background-color: #1e2538; border-left: 4px solid #f59e0b; padding: 14px; border-radius: 6px; margin: 18px 0;\">
                                <p style=\"margin: 0; font-size: 13px; font-weight: bold; color: #f59e0b;\">⚠️ Important Availability Notice:</p>
                                <p style=\"margin: 4px 0 0 0; font-size: 13px;\">Please ensure that you or an authorized representative are present at the pickup location on <strong>" . e($formattedDate) . " at " . e($eventTime) . "</strong> for loading, key access, and item verification.</p>
                            </div>

                            <div style=\"margin: 24px 0; text-align: center;\">
                                <a href=\"{$clientConfirmUrl}\" style=\"background: linear-gradient(135deg, #c9a84c, #b08d35); color: #0d0e15; text-decoration: none; padding: 13px 24px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(201,168,76,0.3);\">
                                    ✅ Confirm & Agree to Schedule
                                </a>
                                <a href=\"{$clientRescheduleUrl}\" style=\"background: #1e2538; color: #f59e0b; border: 1px solid #f59e0b; text-decoration: none; padding: 12px 22px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; margin-left: 10px;\">
                                    📅 Request Reschedule
                                </a>
                            </div>

                            <p style=\"font-size: 13px; color: #94a3b8;\">Our team will arrive at your pickup address at the scheduled time. Click above to confirm or request a schedule adjustment.</p>
                        </div>
                    ";

                    try {
                        \Illuminate\Support\Facades\Mail::to($lead->email)->queue(
                            new \App\Mail\RawCustomEmail($clientSubject, $clientBodyHtml)
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Failed to send customer schedule email to {$lead->email}: " . $e->getMessage());
                    }
                }
            }
        }
        
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type' => 'info',
            'title' => 'Driver Assigned',
            'message' => 'Driver ' . $driver . ' assigned to job ' . $job->id . '.',
            'link' => '/app/jobs?view=' . $job->id,
            'is_read' => false,
        ]);

        

        return response()->json(['message' => 'Driver assigned & email sent', 'job' => $this->present($job->refresh())]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $job = JobEvent::find($id);
        if (!$job) {
            $job = JobEvent::where('lead_id', $id)->where('type', 'Job')->first();
        }
        if (!$job) {
            
            return response()->json(['error' => 'Job not found'], 404);
        }

        $status = $request->input('status'); // booked | assigned | in_progress | completed | completed_won | not_completed
        $allowed = ['booked', 'assigned', 'in_progress', 'completed', 'completed_won', 'not_completed'];
        if (!in_array($status, $allowed, true)) {
            
            return response()->json(['error' => 'Invalid status'], 422);
        }

        $normalStatus = in_array($status, ['completed', 'completed_won'], true) ? 'completed_won' : $status;

        $job->update([
            'status' => $normalStatus,
            'notes'  => $request->input('notes', $job->notes),
        ]);

        // Mirror onto the linked lead so its track-status reflects the outcome.
        if ($job->lead_id) {
            $lead = Lead::find($job->lead_id);
            if ($lead) {
                if (in_array($status, ['completed', 'completed_won'], true)) {
                    $lead->update(['status' => 'won', 'stage' => 'Completed']);
                } elseif ($status === 'not_completed') {
                    $lead->update(['status' => 'not-completed', 'stage' => 'Job']);
                } elseif ($status === 'in_progress') {
                    $lead->update(['status' => 'in_progress', 'stage' => 'Job']);
                } elseif ($status === 'assigned') {
                    $lead->update(['status' => 'assigned', 'stage' => 'Job']);
                } else {
                    $lead->update(['status' => 'job-booked', 'stage' => 'Job']);
                }
            }
        }

        \App\Models\AppNotification::create([
            'user_id' => null,
            'type' => 'info',
            'title' => 'Job Status Updated',
            'message' => 'Job ' . $job->id . ' status changed to ' . strtoupper($normalStatus) . '.',
            'link' => '/app/jobs?view=' . $job->id,
            'is_read' => false,
        ]);

        

        return response()->json(['message' => 'Job status updated', 'job' => $this->present($job->refresh())]);
    }

    /**
     * List all pending job reschedule requests.
     */
    public function rescheduleRequests(Request $request): JsonResponse
    {
        // Find jobs or leads with pending reschedule requests
        $jobs = JobEvent::query()
            ->with('lead')
            ->where('type', 'Job')
            ->where(function ($q) {
                $q->where('client_job_status', 'reschedule_requested')
                  ->orWhere('driver_job_status', 'reschedule_requested')
                  ->orWhereNotNull('job_reschedule_reason');
            })
            ->get();

        $leads = Lead::query()
            ->where(function ($q) {
                $q->where('client_job_status', 'reschedule_requested')
                  ->orWhere('driver_job_status', 'reschedule_requested')
                  ->orWhereNotNull('job_reschedule_reason');
            })
            ->get();

        $requestsMap = [];

        $formatDate = function ($d) {
            if (!$d) return null;
            if ($d instanceof \DateTimeInterface) return $d->format('Y-m-d');
            return substr((string)$d, 0, 10);
        };

        foreach ($jobs as $j) {
            $leadId = (string)($j->lead_id ?: $j->id);
            $requestedBy = ($j->client_job_status === 'reschedule_requested') ? 'Client' : (($j->driver_job_status === 'reschedule_requested') ? 'Driver' : 'User');
            
            $driverName = ($j->driver && $j->driver !== 'Unassigned') ? $j->driver : ($j->lead?->driver_name ?: 'Unassigned Driver');

            $requestsMap[$leadId] = [
                'id'             => $j->id,
                'job_id'         => $j->id,
                'lead_id'        => $leadId,
                'client_name'    => $j->lead?->name ?: 'Valued Client',
                'client_email'   => $j->lead?->email,
                'client_phone'   => $j->lead?->phone,
                'driver_name'    => $driverName,
                'requested_by'   => $requestedBy,
                'current_date'   => $formatDate($j->event_date) ?: ($formatDate($j->lead?->move_date) ?: 'N/A'),
                'current_time'   => $j->event_time ?: ($j->lead?->arrival_window ?: '09:00 AM'),
                'proposed_date'  => $formatDate($j->job_proposed_date) ?: ($formatDate($j->lead?->job_proposed_date) ?: $formatDate($j->event_date)),
                'proposed_time'  => $j->job_proposed_time ?: ($j->lead?->job_proposed_time ?: $j->event_time),
                'reason'         => $j->job_reschedule_reason ?: ($j->lead?->job_reschedule_reason ?: 'No reason provided'),
                'client_status'  => $j->client_job_status ?: $j->lead?->client_job_status,
                'driver_status'  => $j->driver_job_status ?: $j->lead?->driver_job_status,
                'updated_at'     => $this->formatIso($j->updated_at),
            ];
        }

        foreach ($leads as $l) {
            $leadId = (string)$l->id;
            if (!isset($requestsMap[$leadId])) {
                $requestedBy = ($l->client_job_status === 'reschedule_requested') ? 'Client' : (($l->driver_job_status === 'reschedule_requested') ? 'Driver' : 'User');
                $requestsMap[$leadId] = [
                    'id'             => $l->id,
                    'job_id'         => 'J-' . $l->id,
                    'lead_id'        => $leadId,
                    'client_name'    => $l->name ?: 'Valued Client',
                    'client_email'   => $l->email,
                    'client_phone'   => $l->phone,
                    'driver_name'    => $l->driver_name ?: 'Unassigned Driver',
                    'requested_by'   => $requestedBy,
                    'current_date'   => $formatDate($l->move_date) ?: 'N/A',
                    'current_time'   => $l->arrival_window ?: '09:00 AM',
                    'proposed_date'  => $formatDate($l->job_proposed_date) ?: ($formatDate($l->move_date) ?: date('Y-m-d')),
                    'proposed_time'  => $l->job_proposed_time ?: ($l->arrival_window ?: '09:00 AM'),
                    'reason'         => $l->job_reschedule_reason ?: 'No reason provided',
                    'client_status'  => $l->client_job_status,
                    'driver_status'  => $l->driver_job_status,
                    'updated_at'     => $this->formatIso($l->updated_at),
                ];
            }
        }

        

        return response()->json([
            'requests' => array_values($requestsMap),
            'count'    => count($requestsMap),
        ]);
    }

    /**
     * Admin approves a reschedule request and updates date/time everywhere.
     */
    public function approveReschedule(Request $request, string $id): JsonResponse
    {
        $job = JobEvent::find($id);
        if (!$job) {
            $job = JobEvent::where('lead_id', $id)->where('type', 'Job')->first();
        }

        $lead = $job?->lead_id ? Lead::find($job->lead_id) : Lead::find($id);

        if (!$job && !$lead) {
            
            return response()->json(['error' => 'Job or Lead not found'], 404);
        }

        $formatDate = function ($d) {
            if (!$d) return null;
            if ($d instanceof \DateTimeInterface) return $d->format('Y-m-d');
            return substr((string)$d, 0, 10);
        };

        // Target Date & Time (Default to proposed date/time if not explicitly overridden by admin)
        $propDate = $formatDate($job?->job_proposed_date) ?: ($formatDate($lead?->job_proposed_date) ?: date('Y-m-d'));
        $newDate = $request->input('date') ?: $propDate;
        $newTime = $request->input('time') ?: ($job?->job_proposed_time ?: ($lead?->job_proposed_time ?: '09:00 AM'));

        // Reset statuses to pending so BOTH parties must re-confirm the new date & time!
        $scheduleToken = md5(($job?->id ?: $lead?->id) . '_' . time());

        if ($job) {
            $job->update([
                'event_date'            => $newDate,
                'event_time'            => $newTime,
                'client_job_status'     => 'pending',
                'driver_job_status'     => 'pending',
                'status'                => 'not_booked',
                'job_reschedule_reason' => null,
                'job_schedule_token'    => $scheduleToken,
            ]);
        }

        if ($lead) {
            $lead->update([
                'move_date'             => $newDate,
                'arrival_window'        => $newTime,
                'client_job_status'     => 'pending',
                'driver_job_status'     => 'pending',
                'status'                => 'pending',
                'job_reschedule_reason' => null,
                'job_schedule_token'    => $scheduleToken,
            ]);
        }

        $publicBase = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');
        $clientConfirmUrl = "{$publicBase}/job/schedule/confirm?token={$scheduleToken}&role=client";
        $clientRescheduleUrl = "{$publicBase}/job/schedule/reschedule?token={$scheduleToken}&role=client";
        $driverConfirmUrl = "{$publicBase}/job/schedule/confirm?token={$scheduleToken}&role=driver";
        $driverRescheduleUrl = "{$publicBase}/job/schedule/reschedule?token={$scheduleToken}&role=driver";

        $formattedDate = \Carbon\Carbon::parse($newDate)->format('d M Y');
        $clientName = $lead?->name ?: 'Client';
        $driverName = $job?->driver ?: ($lead?->driver_name ?: 'Lead Driver');
        $vehicle    = $job?->vehicle ?: ($lead?->recommended_vehicle ?: 'Removal Truck');

        // Create App Notification
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'success',
            'title'   => '✅ Reschedule Approved by Admin',
            'message' => "Reschedule for Job #" . ($job?->id ?: $lead?->id) . " approved for {$formattedDate} at {$newTime}. Re-confirmation emails sent to Client & Driver.",
            'link'    => '/app/jobs?view=' . ($job?->id ?: $lead?->id),
            'is_read' => false,
        ]);

        // 1. Send Interactive Re-confirmation Email to Client
        if ($lead?->email) {
            $clientSubject = "📅 Reschedule Approved: Please Re-Confirm Move Date ({$formattedDate}) — Lead #" . $lead->id;
            $clientBodyHtml = "
                <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #c9a84c;\">
                    <h2 style=\"color: #c9a84c; margin-top: 0;\">Next Gen Relocation — Reschedule Request Approved</h2>
                    <p style=\"font-size: 14px;\">Dear <strong>" . e($clientName) . "</strong>,</p>
                    <p style=\"font-size: 14px;\">Our operations team has approved the new move schedule. Please review the updated details below and click to confirm your availability:</p>
                    
                    <div style=\"background-color: #141722; border: 1px solid #c9a84c; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">🚚 Approved Move Schedule</h3>
                        <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #f59e0b;\">📅 New Move Date: " . e($formattedDate) . "</p>
                        <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #10b981;\">⏰ New Arrival Window: " . e($newTime) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Lead Driver:</strong> " . e($driverName) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Allocated Vehicle:</strong> " . e($vehicle) . "</p>
                    </div>

                    <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📍 Address Overview</h3>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Pickup Address:</strong> " . e($lead?->from_location ?: 'TBD') . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Destination Address:</strong> " . e($lead?->to_location ?: 'TBD') . "</p>
                    </div>

                    <div style=\"margin: 24px 0; text-align: center;\">
                        <a href=\"{$clientConfirmUrl}\" style=\"background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 13px 24px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(16,185,129,0.3);\">
                            ✅ Confirm & Agree to New Schedule
                        </a>
                        <a href=\"{$clientRescheduleUrl}\" style=\"background: #1e2538; color: #f59e0b; border: 1px solid #f59e0b; text-decoration: none; padding: 12px 22px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; margin-left: 10px;\">
                            📅 Request Further Adjustment
                        </a>
                    </div>

                    <p style=\"font-size: 13px; color: #94a3b8;\">Clicking confirm locks in your new schedule with our relocation team.</p>
                </div>
            ";
            try {
                \Illuminate\Support\Facades\Mail::to($lead->email)->queue(new \App\Mail\RawCustomEmail($clientSubject, $clientBodyHtml));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to send approved reschedule email to client: " . $e->getMessage());
            }
        }

        // 2. Send Interactive Re-confirmation Email to Driver
        $driverUser = \App\Models\User::where('name', $driverName)->first();
        $driverEmail = $driverUser?->email ?: $lead?->driver_email;
        if ($driverEmail) {
            $driverSubject = "🚚 Updated Job Schedule: Re-Confirm Availability for Move #" . ($job?->id ?: $lead?->id);
            $driverBodyHtml = "
                <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #c9a84c;\">
                    <h2 style=\"color: #c9a84c; margin-top: 0;\">Next Gen Relocation — Driver Schedule Adjustment</h2>
                    <p style=\"font-size: 14px;\">Hello <strong>" . e($driverName) . "</strong>,</p>
                    <p style=\"font-size: 14px;\">The relocation move date & time have been updated by operations. Please re-confirm your availability for the new schedule:</p>
                    
                    <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                        <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📋 Updated Job Overview</h3>
                        <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #f59e0b;\">📅 New Move Date: " . e($formattedDate) . "</p>
                        <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #10b981;\">⏰ New Arrival Window: " . e($newTime) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Name:</strong> " . e($clientName) . "</p>
                        <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Assigned Vehicle:</strong> " . e($vehicle) . "</p>
                    </div>

                    <div style=\"margin: 24px 0; text-align: center;\">
                        <a href=\"{$driverConfirmUrl}\" style=\"background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 13px 24px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(16,185,129,0.3);\">
                            ✅ Confirm Availability for New Date
                        </a>
                        <a href=\"{$driverRescheduleUrl}\" style=\"background: #1e2538; color: #f59e0b; border: 1px solid #f59e0b; text-decoration: none; padding: 12px 22px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; margin-left: 10px;\">
                            📅 Request Adjustments
                        </a>
                    </div>

                    <p style=\"font-size: 13px; color: #94a3b8;\">Please click above to confirm your availability for this updated move schedule.</p>
                </div>
            ";
            try {
                \Illuminate\Support\Facades\Mail::to($driverEmail)->queue(new \App\Mail\RawCustomEmail($driverSubject, $driverBodyHtml));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to send approved reschedule email to driver: " . $e->getMessage());
            }
        }

        

        return response()->json([
            'message' => "Reschedule approved! Date & time updated to {$formattedDate} at {$newTime}. Re-confirmation emails sent to both Client & Driver.",
            'job'     => $job ? $this->present($job->refresh()) : null,
        ]);
    }

    /**
     * Admin rejects a reschedule request.
     */
    public function rejectReschedule(Request $request, string $id): JsonResponse
    {
        $job = JobEvent::find($id);
        if (!$job) {
            $job = JobEvent::where('lead_id', $id)->where('type', 'Job')->first();
        }

        $lead = $job?->lead_id ? Lead::find($job->lead_id) : Lead::find($id);

        if ($job) {
            $job->update([
                'client_job_status'     => 'pending',
                'driver_job_status'     => 'pending',
                'job_reschedule_reason' => null,
            ]);
        }

        if ($lead) {
            $lead->update([
                'client_job_status'     => 'pending',
                'driver_job_status'     => 'pending',
                'job_reschedule_reason' => null,
            ]);
        }

        

        return response()->json(['message' => 'Reschedule request dismissed']);
    }

    /**
     * Start Ride & Submit Pre-Ride Inspection (Stage 2)
     */
    public function startRide(Request $request, string $id): JsonResponse
    {
        $job = JobEvent::find($id);
        if (!$job) {
            $job = JobEvent::where('lead_id', $id)->where('type', 'Job')->first();
        }
        $lead = $job?->lead_id ? Lead::find($job->lead_id) : Lead::find($id);

        if (!$job && !$lead) {
            
            return response()->json(['status' => 'error', 'message' => "Job #{$id} not found."], 404);
        }

        $startedAt = $request->input('started_at') ? \Carbon\Carbon::parse($request->input('started_at')) : now();
        $startOdometer = (float)$request->input('start_odometer', 0);

        $selfieUrl = null;
        $meterUrl  = null;
        $backUrl   = null;

        $publicBase = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');

        if ($request->hasFile('selfie_photo')) {
            $path = $request->file('selfie_photo')->store('proofs', 'public');
            $selfieUrl = "{$publicBase}/storage/{$path}";
        }
        if ($request->hasFile('meter_photo')) {
            $path = $request->file('meter_photo')->store('proofs', 'public');
            $meterUrl = "{$publicBase}/storage/{$path}";
        }
        if ($request->hasFile('back_photo')) {
            $path = $request->file('back_photo')->store('proofs', 'public');
            $backUrl = "{$publicBase}/storage/{$path}";
        }

        $updates = [
            'status'          => 'in_progress',
            'started_at'      => $startedAt,
            'start_odometer'  => $startOdometer,
        ];
        if ($selfieUrl) $updates['selfie_url'] = $selfieUrl;
        if ($meterUrl)  $updates['start_meter_url'] = $meterUrl;
        if ($backUrl)   $updates['start_back_url'] = $backUrl;

        if ($job) $job->update($updates);

        if ($lead) {
            $leadUpdates = [
                'status'          => 'in_progress',
                'job_started_at'  => $startedAt,
                'start_odometer'  => $startOdometer,
            ];
            if ($selfieUrl) $leadUpdates['selfie_url'] = $selfieUrl;
            if ($meterUrl)  $leadUpdates['start_meter_url'] = $meterUrl;
            if ($backUrl)   $leadUpdates['start_back_url'] = $backUrl;
            $lead->update($leadUpdates);
        }

        $driverName = $job?->driver ?: ($lead?->driver_name ?: 'Driver');

        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'info',
            'title'   => '🚚 Ride Started by Driver',
            'message' => "Driver {$driverName} started trip for Job #" . ($job?->id ?: $lead?->id) . ". Pre-ride inspection proofs uploaded.",
            'link'    => '/app/jobs?view=' . ($job?->id ?: $lead?->id),
            'is_read' => false,
        ]);

        

        return response()->json([
            'status'  => 'success',
            'message' => 'Ride started and pre-inspection proofs saved.',
            'job'     => $job ? $this->present($job->refresh()) : null,
        ]);
    }

    /**
     * Complete Job & Submit Delivery Proofs (Stage 4)
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $job = JobEvent::find($id);
        if (!$job) {
            $job = JobEvent::where('lead_id', $id)->where('type', 'Job')->first();
        }
        $lead = $job?->lead_id ? Lead::find($job->lead_id) : Lead::find($id);

        if (!$job && !$lead) {
            
            return response()->json(['status' => 'error', 'message' => "Job #{$id} not found."], 404);
        }

        $completedAt  = $request->input('completed_at') ? \Carbon\Carbon::parse($request->input('completed_at')) : now();
        $endOdometer  = (float) $request->input('end_odometer', 0);
        $distanceKm   = (float) $request->input('distance_km', 0);
        $fuelLiters   = (float) $request->input('fuel_liters', 0);
        $notes        = $request->input('notes');

        $startOdometer = (float) ($job?->start_odometer ?: ($lead?->start_odometer ?: 0));

        // Auto-calculate distance if start_odometer exists and distance_km is not explicitly provided
        if ($startOdometer > 0 && $endOdometer > $startOdometer && $distanceKm <= 0) {
            $distanceKm = round($endOdometer - $startOdometer, 2);
        }

        $endMeterUrl = null;
        $endBackUrl  = null;

        $publicBase = rtrim(config('app.url') ?: (env('PUBLIC_BASE_URL') ?: url('/')), '/');

        if ($request->hasFile('end_meter_photo')) {
            $path = $request->file('end_meter_photo')->store('proofs', 'public');
            $endMeterUrl = "{$publicBase}/storage/{$path}";
        }
        if ($request->hasFile('end_back_photo')) {
            $path = $request->file('end_back_photo')->store('proofs', 'public');
            $endBackUrl = "{$publicBase}/storage/{$path}";
        }

        $updates = [
            'status'        => 'completed_won',
            'completed_at'  => $completedAt,
            'end_odometer'  => $endOdometer,
            'distance_km'   => $distanceKm,
            'fuel_liters'   => $fuelLiters,
        ];
        if ($endMeterUrl) $updates['end_meter_url'] = $endMeterUrl;
        if ($endBackUrl)  $updates['end_back_url']  = $endBackUrl;
        if ($notes)       $updates['notes']         = $notes;

        if ($job) $job->update($updates);

        if ($lead) {
            $leadUpdates = [
                'status'           => 'won',
                'stage'            => 'Completed',
                'job_completed_at' => $completedAt,
                'end_odometer'     => $endOdometer,
                'distance_km'      => $distanceKm,
                'fuel_liters'      => $fuelLiters,
            ];
            if ($endMeterUrl) $leadUpdates['end_meter_url'] = $endMeterUrl;
            if ($endBackUrl)  $leadUpdates['end_back_url']  = $endBackUrl;
            $lead->update($leadUpdates);
        }

        $driverName = $job?->driver ?: ($lead?->driver_name ?: 'Driver');

        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'success',
            'title'   => '🎉 Job Delivery Completed',
            'message' => "Job #" . ($job?->id ?: $lead?->id) . " completed and delivered by Driver {$driverName}. Distance: {$distanceKm} km.",
            'link'    => '/app/jobs?view=' . ($job?->id ?: $lead?->id),
            'is_read' => false,
        ]);

        

        return response()->json([
            'status'  => 'success',
            'message' => 'Job marked as delivered successfully.',
            'job'     => $job ? $this->present($job->refresh()) : null,
        ]);
    }
    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'speed'       => 'nullable|numeric',
            'driver_id'   => 'nullable|string',
            'driver_name' => 'nullable|string',
        ]);

        $user = $request->user();
        $driverId = $user ? $user->id : ($data['driver_id'] ?? 'unknown');
        $driverName = $user ? $user->name : ($data['driver_name'] ?? 'Unknown Driver');

        $locationData = [
            'latitude'    => (float) $data['latitude'],
            'longitude'   => (float) $data['longitude'],
            'speed'       => (float) ($data['speed'] ?? 0),
            'driver_id'   => $driverId,
            'driver_name' => $driverName,
            'updated_at'  => now()->toIso8601String(),
        ];

        // Store in cache for 24 hours
        \Illuminate\Support\Facades\Cache::put("driver_location_{$driverId}", $locationData, 86400);
        
        // Also keep a list of active drivers for the map view
        $activeDrivers = \Illuminate\Support\Facades\Cache::get('active_drivers', []);
        $activeDrivers[$driverId] = $locationData;
        \Illuminate\Support\Facades\Cache::put('active_drivers', $activeDrivers, 86400);

        return response()->json([
            'status'  => 'success',
            'message' => 'Location updated successfully',
            'data'    => $locationData,
        ]);
    }
}

