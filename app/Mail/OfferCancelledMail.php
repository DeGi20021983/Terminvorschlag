<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $note,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Absage: '.$this->booking->loadMissing('offer')->offer->title);
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['offer.location', 'slot', 'user']);

        return new Content(markdown: 'mail.offer-cancelled');
    }
}
