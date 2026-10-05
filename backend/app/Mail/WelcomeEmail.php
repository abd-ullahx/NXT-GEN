<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public string $trackingUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to Next Gen Relocation — Inquiry Received ({$this->lead->id})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'lead'        => $this->lead,
                'subject'     => "Welcome to Next Gen Relocation — Inquiry Received ({$this->lead->id})",
                'trackingUrl' => $this->trackingUrl,
            ],
        );
    }
}
