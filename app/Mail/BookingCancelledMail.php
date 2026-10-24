<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $reason  'self' oder 'organizer'
     */
    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Stornierung: '.$this->booking->loadMissing('offer')->offer->title);
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['offer.location', 'slot', 'user']);

        return new Content(markdown: 'mail.booking-cancelled', with: ['byOrganizer' => $this->reason === 'organizer']);
    }
}
