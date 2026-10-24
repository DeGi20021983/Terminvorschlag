<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $changes  Geänderte Felder, z. B. „slot_time“ oder „location_id“
     */
    public function __construct(
        public Booking $booking,
        public array $changes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Änderung: '.$this->booking->loadMissing('offer')->offer->title);
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['offer.location', 'slot', 'user']);

        return new Content(markdown: 'mail.offer-changed', with: [
            'timeChanged' => in_array('slot_time', $this->changes, true)
                || in_array('bookable_from', $this->changes, true)
                || in_array('bookable_until', $this->changes, true),
        ]);
    }
}
