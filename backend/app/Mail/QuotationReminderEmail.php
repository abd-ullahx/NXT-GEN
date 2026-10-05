<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationReminderEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
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
            subject: "Reminder: Your Relocation Quotation Awaits — Next Gen Relocation ({$this->lead->id})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation_reminder',
            with: [
                'lead'        => $this->lead,
                'subject'     => "Reminder: Your Relocation Quotation Awaits — Next Gen Relocation ({$this->lead->id})",
                'approveLink'  => $this->approveLink,
                'trackingUrl'  => $this->trackingUrl,
            ],
        );
    }
}
