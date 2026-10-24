<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Models\Booking;
use App\Services\IcsBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bestätigung: '.$this->booking->loadMissing('offer')->offer->title);
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['offer.location', 'slot', 'user']);

        return new Content(markdown: 'mail.booking-confirmed', with: [
            'confirmationText' => AppSetting::get('confirmation_text', (string) config('terminvorschlag.defaults.confirmation_text')),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(IcsBuilder::class)->forBooking($this->booking), 'termin.ics')
                ->withMime('text/calendar; charset=utf-8'),
        ];
    }
}
