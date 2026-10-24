<?php

namespace App\Services;

use App\Directory\DirectoryService;
use App\Directory\DirectoryUnavailableException;
use App\Exceptions\BookingException;
use App\Mail\ParticipantMessageMail;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Teilnehmerliste: manuell eintragen und Nachrichten an alle gebuchten Personen senden.
 */
final class ParticipantService
{
    public function __construct(
        private readonly DirectoryService $directory,
        private readonly UserProvisioner $provisioner,
        private readonly BookingService $bookings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws BookingException
     * @throws DirectoryUnavailableException
     */
    public function addPerson(User $organizer, Offer $offer, Slot $slot, string $username): Booking
    {
        $person = $this->directory->lookup(strtolower($username));

        if ($person === null) {
            throw BookingException::personNotFound();
        }

        $user = $this->provisioner->sync($person, signedIn: false);

        $booking = $this->bookings->bookManually($organizer, $slot, $user);

        $this->audit->record($organizer, 'booking.manual', $booking, $offer, ['slot_id' => $slot->id]);

        return $booking;
    }

    public function removePerson(User $organizer, Booking $booking): void
    {
        $this->bookings->cancelByOrganizer($booking);

        $this->audit->record($organizer, 'booking.removed', $booking, $booking->offer, ['slot_id' => $booking->slot_id]);
    }

    /**
     * Nachricht an alle gebuchten Personen. Die Anzahl wird protokolliert, der Text nicht.
     */
    public function message(User $organizer, Offer $offer, string $subject, string $body): int
    {
        $recipients = $offer->bookings()->confirmed()->with('user')->get()
            ->map(fn (Booking $booking) => $booking->user)
            ->filter(fn (?User $user) => $user !== null && filled($user->email))
            ->unique('id')
            ->values();

        if ($offer->bookings()->confirmed()->doesntExist()) {
            throw BookingException::messageNeedsRecipients();
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email, $recipient->name())->queue(new ParticipantMessageMail($offer, $subject, $body));
        }

        $this->audit->record($organizer, 'offer.message_sent', $offer, $offer, ['recipients' => $recipients->count()]);

        return $recipients->count();
    }
}
