<?php

namespace App\Listeners;

use App\Enums\AlternativeResponse;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingMoved;
use App\Events\OfferCancelled;
use App\Events\OfferChanged;
use App\Events\WishesReleased;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingMovedMail;
use App\Mail\OfferCancelledMail;
use App\Mail\OfferChangedMail;
use App\Mail\WishProposalMail;
use App\Models\Booking;
use App\Models\User;
use App\Models\WishAssignment;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Verschickt die E-Mails zu Buchungen, Änderungen und Wunschverteilung.
 * Die Mails werden in die Warteschlange gelegt und vom Scheduler abgearbeitet.
 */
final class BookingNotifications
{
    public function bookingConfirmed(BookingConfirmed $event): void
    {
        $this->sendToBooking($event->booking, new BookingConfirmedMail($event->booking));
    }

    public function bookingCancelled(BookingCancelled $event): void
    {
        $this->sendToBooking($event->booking, new BookingCancelledMail($event->booking, $event->reason));
    }

    public function bookingMoved(BookingMoved $event): void
    {
        $this->sendToBooking($event->new, new BookingMovedMail($event->new, $event->old));
    }

    public function offerChanged(OfferChanged $event): void
    {
        $event->offer->bookings()->confirmed()->get()->each(
            fn (Booking $booking) => $this->sendToBooking($booking, new OfferChangedMail($booking, $event->changes)),
        );
    }

    public function offerCancelled(OfferCancelled $event): void
    {
        $event->cancelledBookings->each(
            fn (Booking $booking) => $this->sendToBooking($booking, new OfferCancelledMail($booking, $event->offer->cancellation_note)),
        );
    }

    public function wishesReleased(WishesReleased $event): void
    {
        $event->run->assignments()
            ->where('alternative_response', AlternativeResponse::Pending)
            ->with(['user', 'slot', 'offer'])
            ->get()
            ->each(fn (WishAssignment $assignment) => $this->sendToUser($assignment->user, new WishProposalMail($assignment)));
    }

    private function sendToBooking(Booking $booking, Mailable $mail): void
    {
        $booking->loadMissing('user');

        $this->sendToUser($booking->user, $mail);
    }

    private function sendToUser(?User $user, Mailable $mail): void
    {
        // Ohne dienstliche Adresse gibt es keinen Empfänger. Die Anzeige in der Anwendung bleibt.
        if ($user === null || blank($user->email)) {
            return;
        }

        Mail::to($user->email, $user->name())->queue($mail);
    }
}
