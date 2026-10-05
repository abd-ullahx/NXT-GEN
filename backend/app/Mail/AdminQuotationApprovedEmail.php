<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminQuotationApprovedEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quote,
        public Lead $lead
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Client Approved Quotation — Ready for Booking ({$this->quote->quote_number})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_quotation_approved',
            with: [
                'quote'   => $this->quote,
                'lead'    => $this->lead,
                'subject' => "Client Approved Quotation — Ready for Booking ({$this->quote->quote_number})",
            ],
        );
    }
}
