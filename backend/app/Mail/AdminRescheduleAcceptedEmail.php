<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminRescheduleAcceptedEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Client Accepted Rescheduled Survey Time — Ready for Approval ({$this->lead->id})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_reschedule_accepted',
            with: [
                'lead'    => $this->lead,
                'subject' => "Client Accepted Rescheduled Survey Time — Ready for Approval ({$this->lead->id})",
            ],
        );
    }
}
