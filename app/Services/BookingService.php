<?php

namespace App\Services;

use App\Enums\AlternativeResponse;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\WishRunStatus;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingMoved;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Buchen, Stornieren und Umbuchen.
 *
 * Nebenläufigkeit: Jede Änderung sperrt zuerst die Zeile des Angebots
 * (SELECT … FOR UPDATE). Dadurch laufen Buchungen desselben Angebots nacheinander.
 * Der eindeutige Index auf dem Termin bleibt als zweite Sicherung bestehen.
 */
final class BookingService
{
    private const TRANSACTION_ATTEMPTS = 3;

    private const DUPLICATE_KEY = 1062;

    /**
     * Bestätigt einen freien Termin. Vergibt den Platz sofort.
     */
    public function book(
        User $user,
        Slot $slot,
        BookingSource $source = BookingSource::Instant,
        ?User $bookedBy = null,
    ): Booking {
        $booking = DB::transaction(function () use ($user, $slot, $source, $bookedBy) {
            $offer = $this->lockOffer($slot->offer_id);
            $slot = $this->lockSlot($slot->id);

            $this->assertBookingWindow($offer, $source);
            $this->assertSlotFree($slot, $source);
            $this->assertOnePerPerson($offer, $user);

            return $this->createBooking($offer, $slot, $user, $source, $bookedBy);
        }, self::TRANSACTION_ATTEMPTS);

        event(new BookingConfirmed($booking));

        return $booking;
    }

    /**
     * Trägt eine Person auf Veranlassung des Organisators ein. Die Buchungszeitspanne gilt nicht,
     * Terminbeginn und Belegung dagegen schon.
     */
    public function bookManually(User $organizer, Slot $slot, User $person): Booking
    {
        $booking = DB::transaction(function () use ($organizer, $slot, $person) {
            $offer = $this->lockOffer($slot->offer_id);
            $slot = $this->lockSlot($slot->id);

            $this->assertSlotFree($slot, BookingSource::Manual);
            $this->assertOnePerPerson($offer, $person);

            return $this->createBooking($offer, $slot, $person, BookingSource::Manual, $organizer);
        }, self::TRANSACTION_ATTEMPTS);

        event(new BookingConfirmed($booking));

        return $booking;
    }

    /**
     * Storniert eine eigene Buchung bis zum Terminbeginn. Der Platz ist sofort wieder frei.
     */
    public function cancel(User $user, Booking $booking): Booking
    {
        $cancelled = DB::transaction(function () use ($user, $booking) {
            $this->lockOffer($booking->offer_id);
            $booking = $this->lockBooking($booking->id);

            if ($booking->user_id !== $user->id) {
                throw BookingException::notYourBooking();
            }

            $this->assertActive($booking);

            if ($booking->slot->starts_at->lessThanOrEqualTo(now())) {
                throw BookingException::slotStarted();
            }

            return $this->markCancelled($booking);
        }, self::TRANSACTION_ATTEMPTS);

        event(new BookingCancelled($cancelled, 'self'));

        return $cancelled;
    }

    /**
     * Storniert eine Buchung auf Veranlassung des Organisators (Austragen).
     */
    public function cancelByOrganizer(Booking $booking): Booking
    {
        $cancelled = DB::transaction(function () use ($booking) {
            $this->lockOffer($booking->offer_id);
            $booking = $this->lockBooking($booking->id);

            $this->assertActive($booking);

            return $this->markCancelled($booking);
        }, self::TRANSACTION_ATTEMPTS);

        event(new BookingCancelled($cancelled, 'organizer'));

        return $cancelled;
    }

    /**
     * Bucht eine bestehende Buchung auf einen anderen freien Termin desselben Angebots um.
     *
     * In einer Transaktion: Der neue Platz wird angelegt, der alte freigegeben. Scheitert
     * ein Schritt, bleibt der alte Platz vollständig bestehen.
     */
    public function move(User $user, Booking $booking, Slot $newSlot): Booking
    {
        if ($newSlot->offer_id !== $booking->offer_id) {
            throw BookingException::slotNotInOffer();
        }

        [$new, $old] = DB::transaction(function () use ($user, $booking, $newSlot) {
            $offer = $this->lockOffer($booking->offer_id);
            $old = $this->lockBooking($booking->id);

            if ($old->user_id !== $user->id) {
                throw BookingException::notYourBooking();
            }

            $this->assertActive($old);

            if ($old->slot->starts_at->lessThanOrEqualTo(now())) {
                throw BookingException::slotStarted();
            }

            $this->assertBookingWindow($offer, BookingSource::Moved);

            $slot = $this->lockSlot($newSlot->id);
            $this->assertSlotFree($slot, BookingSource::Moved);

            $new = $this->createBooking($offer, $slot, $user, BookingSource::Moved, $user, $old->id);

            $old = $this->markCancelled($old);

            return [$new, $old];
        }, self::TRANSACTION_ATTEMPTS);

        event(new BookingMoved($new, $old));

        return $new;
    }

    private function lockOffer(int $offerId): Offer
    {
        return Offer::query()->whereKey($offerId)->lockForUpdate()->firstOrFail();
    }

    private function lockSlot(int $slotId): Slot
    {
        return Slot::query()->whereKey($slotId)->lockForUpdate()->firstOrFail();
    }

    private function lockBooking(int $bookingId): Booking
    {
        return Booking::query()->with('slot')->whereKey($bookingId)->lockForUpdate()->firstOrFail();
    }

    private function assertBookingWindow(Offer $offer, BookingSource $source): void
    {
        if (! $offer->isInBookingWindow(now())) {
            throw BookingException::outsideWindow();
        }

        // Bei Wunschterminen ist Sofortbuchung erst nach der Freigabe offen (Umbuchen ebenso).
        if ($source !== BookingSource::Wish && ! $offer->instantBookingOpen()) {
            throw BookingException::notBookableNow();
        }
    }

    private function assertSlotFree(Slot $slot, BookingSource $source): void
    {
        if ($slot->starts_at->lessThanOrEqualTo(now())) {
            throw BookingException::slotStarted();
        }

        if (Booking::query()->confirmed()->where('slot_id', $slot->id)->exists()) {
            throw BookingException::slotTaken();
        }

        // Ein offener Vorschlag aus der Wunschverteilung reserviert den Termin gegen Sofortbuchung und Umbuchung.
        if ($source !== BookingSource::Wish && $this->reservedByWishProposal($slot)) {
            throw BookingException::slotTaken();
        }
    }

    private function reservedByWishProposal(Slot $slot): bool
    {
        return WishAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('alternative_response', AlternativeResponse::Pending)
            ->whereHas('run', fn (Builder $run) => $run->where('status', WishRunStatus::Released))
            ->exists();
    }

    private function assertOnePerPerson(Offer $offer, User $user, ?int $exceptBookingId = null): void
    {
        if (! $offer->one_booking_per_person) {
            return;
        }

        $query = Booking::query()->confirmed()
            ->where('offer_id', $offer->id)
            ->where('user_id', $user->id);

        if ($exceptBookingId !== null) {
            $query->whereKeyNot($exceptBookingId);
        }

        if ($query->exists()) {
            throw BookingException::alreadyBooked();
        }
    }

    private function assertActive(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw BookingException::bookingNotActive();
        }
    }

    private function createBooking(
        Offer $offer,
        Slot $slot,
        User $user,
        BookingSource $source,
        ?User $bookedBy,
        ?int $replacesBookingId = null,
    ): Booking {
        try {
            return Booking::query()->create([
                'offer_id' => $offer->id,
                'slot_id' => $slot->id,
                'user_id' => $user->id,
                'booked_by_user_id' => $bookedBy?->id,
                'replaces_booking_id' => $replacesBookingId,
                'status' => BookingStatus::Confirmed,
                'source' => $source,
                'booked_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === self::DUPLICATE_KEY) {
                throw BookingException::slotTaken();
            }

            throw $exception;
        }
    }

    private function markCancelled(Booking $booking): Booking
    {
        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $booking;
    }
}
