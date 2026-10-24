<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingMovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $new,
        public Booking $old,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Umbuchung: '.$this->new->loadMissing('offer')->offer->title);
    }

    public function content(): Content
    {
        $this->new->loadMissing(['offer.location', 'slot', 'user']);
        $this->old->loadMissing('slot');

        return new Content(markdown: 'mail.booking-moved');
    }
}
