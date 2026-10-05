<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\SurveyReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyDayReminderEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public string $subjectText;
    public string $urgencyNote;
    public string $dateStr;
    public string $timeStr;
    public string $surveyTypeStr;

    public function __construct(
        public Lead $lead,
        public SurveyReminder $reminder,
        public string $trackingUrl
    ) {
        $this->dateStr = $this->lead->survey_requested_date
            ? \Carbon\Carbon::parse($this->lead->survey_requested_date)->format('l, j F Y')
            : 'your scheduled date';
        $this->timeStr       = $this->lead->survey_requested_time_range ?? 'your booked time';
        $this->surveyTypeStr = ucfirst($this->lead->survey_type ?? 'survey');

        $subjects = [
            '1_day'   => "Reminder: Your Survey is Tomorrow — Next Gen Relocation ({$this->lead->id})",
            '6_hours' => "Reminder: Your Survey is in 6 Hours — Next Gen Relocation ({$this->lead->id})",
            '1_hour'  => "Reminder: Your Survey is in 1 Hour — Next Gen Relocation ({$this->lead->id})",
            '15_min'  => "Reminder: Your Survey Starts in 15 Minutes! — Next Gen Relocation ({$this->lead->id})",
        ];
        $this->subjectText = $subjects[$this->reminder->type] ?? "Survey Reminder — Next Gen Relocation ({$this->lead->id})";

        $this->urgencyNote = match ($this->reminder->type) {
            '1_day'   => 'Your survey is scheduled for <strong>tomorrow</strong>.',
            '6_hours' => 'Your survey is in just <strong>6 hours</strong>.',
            '1_hour'  => 'Your survey is in <strong>1 hour</strong> — please be ready!',
            '15_min'  => '⏰ <strong>Your survey starts in 15 minutes!</strong> Please be available now.',
            default   => 'Your survey is coming up soon.',
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.survey_day_reminder',
            with: [
                'lead'          => $this->lead,
                'reminder'      => $this->reminder,
                'subject'       => $this->subjectText,
                'urgencyNote'   => $this->urgencyNote,
                'date'          => $this->dateStr,
                'time'          => $this->timeStr,
                'surveyType'    => $this->surveyTypeStr,
                'trackingUrl'   => $this->trackingUrl,
            ],
        );
    }
}
