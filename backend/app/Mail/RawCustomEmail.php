<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RawCustomEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $bodyContent,
        public ?string $trackingUrl = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.raw_custom',
            with: [
                'subject'     => $this->subjectText,
                'bodyContent' => $this->bodyContent,
                'trackingUrl' => $this->trackingUrl,
            ],
        );
    }
}
