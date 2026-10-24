<?php

namespace App\Mail;

use App\Models\WishAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WishProposalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public WishAssignment $assignment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vorschlag für Ihren Wunschtermin: '.$this->assignment->loadMissing('offer')->offer->title);
    }

    public function content(): Content
    {
        $this->assignment->loadMissing(['offer.location', 'slot', 'user']);

        return new Content(markdown: 'mail.wish-proposal');
    }
}
