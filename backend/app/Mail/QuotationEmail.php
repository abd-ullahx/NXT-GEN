<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $isPostSurvey;
    public string $subjectText;

    public function __construct(
        public Quotation $quote,
        public ?Lead $lead,
        public string $approveLink,
        public string $trackingUrl
    ) {
        $this->isPostSurvey = $this->quote->quote_type === 'post-survey';
        $this->subjectText  = $this->isPostSurvey
            ? "Your Final Moving Quotation — Next Gen Relocation ({$this->quote->quote_number})"
            : "Your Indicative Quotation — Next Gen Relocation ({$this->quote->quote_number})";
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
            view: 'emails.quotation',
            with: [
                'quote'        => $this->quote,
                'lead'         => $this->lead,
                'isPostSurvey' => $this->isPostSurvey,
                'subject'      => $this->subjectText,
                'approveLink'  => $this->approveLink,
                'trackingUrl'  => $this->trackingUrl,
            ],
        );
    }
}
