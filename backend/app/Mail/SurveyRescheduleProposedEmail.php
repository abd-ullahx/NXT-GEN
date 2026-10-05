<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyRescheduleProposedEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public string $approveLink,
        public string $trackingUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action Required: Proposed New Survey Schedule — Next Gen Relocation ({$this->lead->id})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.survey_reschedule_proposed',
            with: [
                'lead'        => $this->lead,
                'approveLink' => $this->approveLink,
                'subject'     => "Action Required: Proposed New Survey Schedule — Next Gen Relocation ({$this->lead->id})",
                'trackingUrl' => $this->trackingUrl,
            ],
        );
    }
}
