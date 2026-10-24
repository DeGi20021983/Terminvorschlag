<?php

namespace App\Mail;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nachricht des Organisators an alle Gebuchten eines Angebots. Reiner Text, kein HTML.
 */
class ParticipantMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Offer $offer,
        public string $messageSubject,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->messageSubject.' · '.$this->offer->title);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.participant-message');
    }
}
