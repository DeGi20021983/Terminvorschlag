<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OfferStatus;
use App\Events\OfferCancelled;
use App\Events\OfferChanged;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Angebote und Termine anlegen, ändern, veröffentlichen und absagen.
 * Jede Änderung durch einen Organisator wird protokolliert.
 */
final class OfferService
{
    private const MAX_GRID_SLOTS = 200;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $organizer, array $data): Offer
    {
        return DB::transaction(function () use ($organizer, $data) {
            $offer = Offer::query()->create($data + [
                'owner_user_id' => $organizer->id,
                'status' => OfferStatus::Draft,
            ]);

            $this->audit->record($organizer, 'offer.created', $offer, $offer, ['title' => $offer->title]);

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $organizer, Offer $offer, array $data): Offer
    {
        return DB::transaction(function () use ($organizer, $offer, $data) {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            $offer->fill($data);
            $changes = array_keys($offer->getDirty());

            if ($changes === []) {
                return $offer;
            }

            $offer->save();

            $this->audit->record($organizer, 'offer.updated', $offer, $offer, ['fields' => $changes]);

            if ($offer->status === OfferStatus::Published) {
                $relevant = array_values(array_intersect($changes, [
                    'bookable_from', 'bookable_until', 'location_id', 'online_url', 'title', 'contact_name',
                ]));

                if ($relevant !== []) {
                    DB::afterCommit(fn () => event(new OfferChanged($offer, $relevant)));
                }
            }

            return $offer;
        });
    }

    public function publish(User $organizer, Offer $offer): Offer
    {
        if ($offer->slots()->doesntExist()) {
            throw BookingException::offerNotPublishable();
        }

        $offer->update(['status' => OfferStatus::Published]);

        $this->audit->record($organizer, 'offer.published', $offer, $offer);

        return $offer;
    }

    /**
     * Sagt das Angebot ab. Alle bestätigten Buchungen werden storniert, die Personen
     * werden über den Versand von Benachrichtigungen informiert (Event).
     */
    public function cancel(User $organizer, Offer $offer, ?string $note = null): Offer
    {
        $cancelled = DB::transaction(function () use ($organizer, $offer, $note) {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($offer->isCancelled()) {
                throw BookingException::offerAlreadyCancelled();
            }

            $bookings = Booking::query()->confirmed()
                ->where('offer_id', $offer->id)
                ->with('slot')
                ->get();

            Booking::query()->whereIn('id', $bookings->pluck('id'))->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $offer->update([
                'status' => OfferStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_note' => $note,
            ]);

            $this->audit->record($organizer, 'offer.cancelled', $offer, $offer, [
                'cancelled_bookings' => $bookings->count(),
            ]);

            return [$offer, $bookings];
        });

        [$offer, $bookings] = $cancelled;

        DB::afterCommit(fn () => event(new OfferCancelled($offer, $bookings)));

        return $offer;
    }

    /**
     * Legt einen einzelnen Termin an. Überschneidungen mit bestehenden Terminen werden abgelehnt.
     */
    public function addSlot(User $organizer, Offer $offer, CarbonImmutable $start, CarbonImmutable $end): Slot
    {
        $this->assertValidRange($start, $end);

        return DB::transaction(function () use ($organizer, $offer, $start, $end) {
            $this->lockOffer($offer);
            $this->assertNoOverlap($offer, $start, $end);

            $slot = Slot::query()->create([
                'offer_id' => $offer->id,
                'starts_at' => $start,
                'ends_at' => $end,
            ]);

            $this->audit->record($organizer, 'slot.created', $slot, $offer);

            return $slot;
        });
    }

    /**
     * Zeitraster für einen Tag, z. B. 9:00 bis 12:00 in 30-Minuten-Schritten.
     *
     * @return list<Slot>
     */
    public function addGrid(
        User $organizer,
        Offer $offer,
        CarbonImmutable $day,
        string $from,
        string $to,
        int $minutes,
    ): array {
        $start = $day->setTimeFromTimeString($from);
        $end = $day->setTimeFromTimeString($to);

        $this->assertValidRange($start, $end);

        $total = (int) $start->diffInMinutes($end);

        if ($total % $minutes !== 0) {
            throw BookingException::gridNotDivisible($minutes);
        }

        $count = intdiv($total, $minutes);

        if ($count > self::MAX_GRID_SLOTS) {
            throw BookingException::gridTooLarge(self::MAX_GRID_SLOTS);
        }

        return DB::transaction(function () use ($organizer, $offer, $start, $minutes, $count) {
            $this->lockOffer($offer);

            $slots = [];

            for ($i = 0; $i < $count; $i++) {
                $slotStart = $start->addMinutes($i * $minutes);
                $slotEnd = $slotStart->addMinutes($minutes);

                $this->assertNoOverlap($offer, $slotStart, $slotEnd);

                $slots[] = Slot::query()->create([
                    'offer_id' => $offer->id,
                    'starts_at' => $slotStart,
                    'ends_at' => $slotEnd,
                ]);
            }

            $this->audit->record($organizer, 'slot.grid', null, $offer, [
                'count' => count($slots),
                'minutes' => $minutes,
            ]);

            return $slots;
        });
    }

    /**
     * Kopiert einen Termin auf einen neuen Startzeitpunkt, die Dauer bleibt gleich.
     */
    public function copySlot(User $organizer, Slot $slot, CarbonImmutable $newStart): Slot
    {
        $duration = (int) $slot->starts_at->diffInMinutes($slot->ends_at);
        $newEnd = $newStart->addMinutes($duration);

        return DB::transaction(function () use ($organizer, $slot, $newStart, $newEnd) {
            $offer = $this->lockOffer($slot->offer);
            $this->assertNoOverlap($offer, $newStart, $newEnd);

            $copy = Slot::query()->create([
                'offer_id' => $offer->id,
                'starts_at' => $newStart,
                'ends_at' => $newEnd,
            ]);

            $this->audit->record($organizer, 'slot.copied', $copy, $offer, ['source_slot_id' => $slot->id]);

            return $copy;
        });
    }

    /**
     * Verlegt einen Termin. Bei belegten Terminen werden die Personen informiert.
     */
    public function moveSlot(User $organizer, Slot $slot, CarbonImmutable $newStart, CarbonImmutable $newEnd): Slot
    {
        $this->assertValidRange($newStart, $newEnd);

        return DB::transaction(function () use ($organizer, $slot, $newStart, $newEnd) {
            $offer = $this->lockOffer($slot->offer);
            $slot = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();

            $this->assertNoOverlap($offer, $newStart, $newEnd, $slot->id);

            $oldStart = $slot->starts_at->toImmutable();

            $slot->update(['starts_at' => $newStart, 'ends_at' => $newEnd]);

            $this->audit->record($organizer, 'slot.moved', $slot, $offer, [
                'from' => $oldStart->toIso8601String(),
                'to' => $newStart->toIso8601String(),
            ]);

            if ($slot->bookings()->confirmed()->exists()) {
                DB::afterCommit(fn () => event(new OfferChanged($offer, ['slot_time'])));
            }

            return $slot;
        });
    }

    /**
     * Löscht einen unbelegten Termin samt abgesagter Buchungen und Wünschen.
     */
    public function deleteSlot(User $organizer, Slot $slot): void
    {
        DB::transaction(function () use ($organizer, $slot) {
            $offer = $this->lockOffer($slot->offer);
            $slot = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();

            if ($slot->bookings()->confirmed()->exists()) {
                throw BookingException::slotHasBookings();
            }

            $slot->bookings()->delete();
            $slot->delete();

            $this->audit->record($organizer, 'slot.deleted', null, $offer, ['slot_id' => $slot->id]);
        });
    }

    private function lockOffer(Offer $offer): Offer
    {
        return Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
    }

    private function assertValidRange(CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw BookingException::invalidTime();
        }
    }

    private function assertNoOverlap(Offer $offer, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreSlotId = null): void
    {
        $query = Slot::query()
            ->where('offer_id', $offer->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);

        if ($ignoreSlotId !== null) {
            $query->whereKeyNot($ignoreSlotId);
        }

        if ($query->exists()) {
            throw BookingException::overlapping();
        }
    }
}
