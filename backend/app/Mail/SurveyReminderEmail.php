<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyReminderEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public string $bookLink,
        public string $trackingUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: Book Your Relocation Survey — Next Gen Relocation ({$this->lead->id})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.survey_reminder',
            with: [
                'lead'        => $this->lead,
                'subject'     => "Reminder: Book Your Relocation Survey — Next Gen Relocation ({$this->lead->id})",
                'bookLink'    => $this->bookLink,
                'trackingUrl' => $this->trackingUrl,
            ],
        );
    }
}
