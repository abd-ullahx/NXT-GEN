<?php

namespace App\Http\Controllers;

use App\Models\JobEvent;
use App\Models\Lead;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\RawCustomEmail;

class JobScheduleController extends Controller
{
    /**
     * Handle Confirm Click from Client or Driver email.
     */
    public function confirm(Request $request)
    {
        $token = $request->query('token');
        $role  = strtolower($request->query('role', 'client')); // 'client' or 'driver'

        if (!$token) {
            return $this->renderMessagePage('Invalid Link', 'No valid schedule token provided.', false);
        }

        $job = JobEvent::where('job_schedule_token', $token)->first();
        $lead = $job ? Lead::find($job->lead_id) : Lead::where('job_schedule_token', $token)->first();

        if (!$job && !$lead) {
            return $this->renderMessagePage('Schedule Not Found', 'The requested job schedule link is invalid or expired.', false);
        }

        $partyName = $role === 'driver' ? ($job?->driver ?: $lead?->driver_name ?: 'Driver') : ($lead?->name ?: 'Client');
        $eventDate = $job?->event_date?->format('d M Y') ?: ($lead?->move_date?->format('d M Y') ?: 'Scheduled Date');
        $eventTime = $job?->event_time ?: ($lead?->arrival_window ?: '09:00 AM');

        if ($role === 'driver') {
            if ($job) $job->update(['driver_job_status' => 'confirmed']);
            if ($lead) $lead->update(['driver_job_status' => 'confirmed']);
            $title = "Driver Confirmed Job Schedule";
            $msg = "Driver {$partyName} has confirmed availability for Job #" . ($job?->id ?: $lead?->id) . " on {$eventDate} at {$eventTime}.";
        } else {
            if ($job) $job->update(['client_job_status' => 'confirmed']);
            if ($lead) $lead->update(['client_job_status' => 'confirmed']);
            $title = "Client Confirmed Job Schedule";
            $msg = "Client {$partyName} has confirmed availability for Job #" . ($job?->id ?: $lead?->id) . " on {$eventDate} at {$eventTime}.";
        }

        AppNotification::create([
            'user_id' => null,
            'type'    => 'success',
            'title'   => $title,
            'message' => $msg,
            'link'    => '/app/jobs?view=' . ($job?->id ?: $lead?->id),
            'is_read' => false,
        ]);

        // Refresh instances
        if ($job) $job->refresh();
        if ($lead) $lead->refresh();

        $clientConfirmed = ($job?->client_job_status === 'confirmed') || ($lead?->client_job_status === 'confirmed');
        $driverConfirmed = ($job?->driver_job_status === 'confirmed') || ($lead?->driver_job_status === 'confirmed');

        // If both parties have confirmed, lock in status as 'booked' and send final mutual confirmation email!
        if ($clientConfirmed && $driverConfirmed) {
            if ($job) $job->update(['status' => 'booked']);
            if ($lead) $lead->update(['status' => 'booked', 'stage' => 'Booking Confirmed']);
            $this->sendFinalMutualConfirmation($job, $lead);
        } else {
            if ($job) $job->update(['status' => 'not_booked']);
            if ($lead) $lead->update(['status' => 'pending']);
        }

        return $this->renderMessagePage(
            'Schedule Confirmed!',
            "Thank you, <strong>" . e($partyName) . "</strong>! You have successfully confirmed and agreed to the move schedule on <strong>" . e($eventDate) . " at " . e($eventTime) . "</strong>.",
            true
        );
    }

    /**
     * Render HTML Form for Reschedule Request.
     */
    public function showRescheduleForm(Request $request)
    {
        $token = $request->query('token');
        $role  = strtolower($request->query('role', 'client'));

        if (!$token) {
            return $this->renderMessagePage('Invalid Link', 'No valid schedule token provided.', false);
        }

        $job = JobEvent::where('job_schedule_token', $token)->first();
        $lead = $job ? Lead::find($job->lead_id) : Lead::where('job_schedule_token', $token)->first();

        if (!$job && !$lead) {
            return $this->renderMessagePage('Schedule Not Found', 'The requested job schedule link is invalid or expired.', false);
        }

        $partyName = $role === 'driver' ? ($job?->driver ?: $lead?->driver_name ?: 'Driver') : ($lead?->name ?: 'Client');
        $currentDate = $job?->event_date?->format('Y-m-d') ?: ($lead?->move_date?->format('Y-m-d') ?: date('Y-m-d'));
        $currentTime = $job?->event_time ?: ($lead?->arrival_window ?: '09:00 AM');

        return response("
            <!DOCTYPE html>
            <html lang=\"en\">
            <head>
                <meta charset=\"UTF-8\">
                <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                <title>Request Schedule Adjustment — Next Gen Relocation</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0b0d14; color: #e2e8f0; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 90vh; }
                    .card { background-color: #141722; border: 1px solid #c9a84c; border-radius: 20px; padding: 35px; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
                    h2 { color: #c9a84c; margin-top: 0; font-size: 24px; text-align: center; }
                    p { color: #94a3b8; font-size: 14px; text-align: center; margin-bottom: 25px; }
                    label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; }
                    input, textarea, select { width: 100%; box-sizing: border-box; padding: 12px; border-radius: 10px; border: 1px solid #2a2e3d; background-color: #0d0e15; color: #fff; font-size: 14px; margin-bottom: 18px; outline: none; }
                    input:focus, textarea:focus { border-color: #c9a84c; }
                    button { width: 100%; padding: 14px; border-radius: 12px; border: none; background: linear-gradient(135deg, #c9a84c, #e2c26d); color: #0d0e15; font-size: 15px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
                    button:hover { opacity: 0.9; }
                </style>
            </head>
            <body>
                <div class=\"card\">
                    <h2>📅 Request Job Reschedule</h2>
                    <p>Hello <strong>" . e($partyName) . "</strong>, please specify your preferred new date, time, and reason below:</p>
                    <form method=\"POST\" action=\"/job/schedule/reschedule\">
                        <input type=\"hidden\" name=\"token\" value=\"" . e($token) . "\">
                        <input type=\"hidden\" name=\"role\" value=\"" . e($role) . "\">
                        
                        <label>Proposed New Date *</label>
                        <input type=\"date\" name=\"proposed_date\" value=\"" . e($currentDate) . "\" required>

                        <label>Proposed Arrival Time *</label>
                        <input type=\"text\" name=\"proposed_time\" value=\"" . e($currentTime) . "\" placeholder=\"e.g. 10:00 AM or Morning\" required>

                        <label>Reason for Reschedule Request *</label>
                        <textarea name=\"reason\" rows=\"4\" placeholder=\"Please explain why you need to adjust this move schedule...\" required></textarea>

                        <button type=\"submit\">Submit Reschedule Request</button>
                    </form>
                </div>
            </body>
            </html>
        ");
    }

    /**
     * Process Reschedule Submission.
     */
    public function submitReschedule(Request $request)
    {
        $token = $request->input('token');
        $role  = strtolower($request->input('role', 'client'));
        $proposedDate = $request->input('proposed_date');
        $proposedTime = $request->input('proposed_time');
        $reason       = $request->input('reason');

        if (!$token) {
            return $this->renderMessagePage('Invalid Request', 'No schedule token provided.', false);
        }

        $job = JobEvent::where('job_schedule_token', $token)->first();
        $lead = $job ? Lead::find($job->lead_id) : Lead::where('job_schedule_token', $token)->first();

        if (!$job && !$lead) {
            return $this->renderMessagePage('Schedule Not Found', 'The requested schedule record was not found.', false);
        }

        $partyName = $role === 'driver' ? ($job?->driver ?: $lead?->driver_name ?: 'Driver') : ($lead?->name ?: 'Client');

        $updates = [
            'job_reschedule_reason' => $reason,
            'job_proposed_date'     => $proposedDate,
            'job_proposed_time'     => $proposedTime,
        ];

        if ($role === 'driver') {
            $updates['driver_job_status'] = 'reschedule_requested';
        } else {
            $updates['client_job_status'] = 'reschedule_requested';
        }

        if ($job) $job->update($updates);
        if ($lead) $lead->update($updates);

        // Urgent AppNotification for Admin
        AppNotification::create([
            'user_id' => null,
            'type'    => 'warning',
            'title'   => "⚠️ Reschedule Requested by {$partyName}",
            'message' => ucfirst($role) . " {$partyName} requested schedule change for Job #" . ($job?->id ?: $lead?->id) . ". Proposed: {$proposedDate} at {$proposedTime}. Reason: " . substr($reason, 0, 100),
            'link'    => '/app/jobs?view=' . ($job?->id ?: $lead?->id),
            'is_read' => false,
        ]);

        // Send Email Alert to Admin
        $adminEmail = 'admin@nextgenrelocation.co.uk';
        $adminSubject = "⚠️ Schedule Adjustment Request: Job #" . ($job?->id ?: $lead?->id) . " ({$partyName})";
        $adminBody = "
            <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 1px solid #f59e0b;\">
                <h2 style=\"color: #f59e0b; margin-top: 0;\">⚠️ Job Reschedule Request Received</h2>
                <p><strong>Requesting Party:</strong> " . ucfirst($role) . " — " . e($partyName) . "</p>
                <p><strong>Job / Lead ID:</strong> " . e($job?->id ?: $lead?->id) . "</p>
                <p><strong>Proposed New Date:</strong> " . e($proposedDate) . "</p>
                <p><strong>Proposed New Time:</strong> " . e($proposedTime) . "</p>
                <div style=\"background-color: #141722; border: 1px solid #2a2e3d; padding: 15px; border-radius: 10px; margin: 15px 0;\">
                    <p style=\"margin: 0; color: #c9a84c; font-weight: bold;\">Reason Provided:</p>
                    <p style=\"margin-top: 5px; font-size: 14px;\">" . nl2br(e($reason)) . "</p>
                </div>
                <p>Please open the Next Gen CRM Operations Dashboard to review and reassign/reschedule this job.</p>
            </div>
        ";

        try {
            Mail::to($adminEmail)->queue(new RawCustomEmail($adminSubject, $adminBody));
        } catch (\Throwable $e) {
            Log::warning("Failed to send admin reschedule alert email: " . $e->getMessage());
        }

        return $this->renderMessagePage(
            'Reschedule Request Submitted',
            "Thank you, <strong>" . e($partyName) . "</strong>! Your request to reschedule to <strong>" . e($proposedDate) . " at " . e($proposedTime) . "</strong> has been submitted to our operations team. We will contact you shortly.",
            true
        );
    }

    /**
     * Dispatch Final Confirmation Email when both parties confirm.
     */
    private function sendFinalMutualConfirmation(?JobEvent $job, ?Lead $lead): void
    {
        $leadObj = $lead ?: ($job?->lead_id ? Lead::find($job->lead_id) : null);
        if (!$leadObj) return;

        $moveDate = $job?->event_date?->format('d M Y') ?: ($leadObj->move_date?->format('d M Y') ?: 'Scheduled Date');
        $arrivalTime = $job?->event_time ?: ($leadObj->arrival_window ?: '09:00 AM');
        $driverName = $job?->driver ?: ($leadObj->driver_name ?: 'Lead Driver');
        $vehicle = $job?->vehicle ?: ($leadObj->recommended_vehicle ?: 'Removal Truck');
        $pickup = $leadObj->from_location ?: 'TBD';
        $dest = $leadObj->to_location ?: 'TBD';

        $subject = "🎉 OFFICIAL CONFIRMATION: Relocation Move Confirmed — Job #" . ($job?->id ?: $leadObj->id);

        $bodyHtml = "
            <div style=\"font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d0e15; color: #e2e8f0; padding: 25px; border-radius: 16px; border: 2px solid #10b981;\">
                <h2 style=\"color: #10b981; margin-top: 0; text-align: center;\">🎉 Relocation Schedule Confirmed by All Parties</h2>
                <p style=\"font-size: 14px; text-align: center;\">Both the client and driver team have confirmed the schedule. The move is locked in!</p>
                
                <div style=\"background-color: #141722; border: 1px solid #10b981; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                    <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">🗓️ Final Move Schedule</h3>
                    <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #f59e0b;\">📅 Relocation Date: " . e($moveDate) . "</p>
                    <p style=\"margin: 6px 0; font-size: 15px; font-weight: bold; color: #10b981;\">⏰ Arrival Window: " . e($arrivalTime) . "</p>
                    <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Client Name:</strong> " . e($leadObj->name) . "</p>
                    <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Lead Driver:</strong> " . e($driverName) . "</p>
                    <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Allocated Vehicle:</strong> " . e($vehicle) . "</p>
                </div>

                <div style=\"background-color: #141722; border: 1px solid #2a2e3d; border-radius: 12px; padding: 18px; margin: 18px 0;\">
                    <h3 style=\"color: #c9a84c; font-size: 15px; border-b: 1px solid #2a2e3d; padding-bottom: 8px; margin-top: 0;\">📍 Addresses</h3>
                    <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Pickup:</strong> " . e($pickup) . "</p>
                    <p style=\"margin: 6px 0; font-size: 13px;\"><strong>Destination:</strong> " . e($dest) . "</p>
                </div>

                <p style=\"font-size: 13px; color: #94a3b8; text-align: center;\">Thank you for choosing Next Gen Relocation. We look forward to executing a seamless move!</p>
            </div>
        ";

        // Send to Client
        if ($leadObj->email) {
            try {
                Mail::to($leadObj->email)->queue(new RawCustomEmail($subject, $bodyHtml));
            } catch (\Throwable $e) {
                Log::warning("Failed to send final confirmation to client {$leadObj->email}: " . $e->getMessage());
            }
        }

        // Send to Driver
        $driverEmail = $leadObj->driver_email;
        if (!$driverEmail && $driverName) {
            $user = \App\Models\User::where('name', $driverName)->first();
            $driverEmail = $user?->email;
        }

        if ($driverEmail) {
            try {
                Mail::to($driverEmail)->queue(new RawCustomEmail($subject, $bodyHtml));
            } catch (\Throwable $e) {
                Log::warning("Failed to send final confirmation to driver {$driverEmail}: " . $e->getMessage());
            }
        }
    }

    /**
     * Helper to render clean response page.
     */
    private function renderMessagePage(string $title, string $message, bool $success)
    {
        $color = $success ? '#c9a84c' : '#ef4444';
        return response("
            <!DOCTYPE html>
            <html lang=\"en\">
            <head>
                <meta charset=\"UTF-8\">
                <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                <title>" . e($title) . " — Next Gen Relocation</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0b0d14; color: #e2e8f0; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 90vh; }
                    .card { background-color: #141722; border: 1px solid {$color}; border-radius: 20px; padding: 40px; width: 100%; max-width: 480px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
                    h2 { color: {$color}; margin-top: 0; font-size: 26px; }
                    p { color: #cbd5e1; font-size: 15px; line-height: 1.6; }
                </style>
            </head>
            <body>
                <div class=\"card\">
                    <h2>" . e($title) . "</h2>
                    <p>{$message}</p>
                </div>
            </body>
            </html>
        ");
    }
}
