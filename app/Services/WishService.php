<?php

namespace App\Services;

use App\Enums\AlternativeResponse;
use App\Enums\BookingSource;
use App\Enums\WishRunStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\User;
use App\Models\WishAssignment;
use App\Models\WishEntry;
use Illuminate\Support\Facades\DB;

/**
 * Wünsche der Mitarbeitenden: abgeben bis zur Frist, Vorschläge annehmen oder ablehnen.
 */
final class WishService
{
    private const MAX_WISHES = 3;

    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * @param  array<int, int|string|null>  $slotIds  Schlüssel 1 bis 3 für Wunsch 1 bis 3
     */
    public function submit(User $user, Offer $offer, array $slotIds): void
    {
        DB::transaction(function () use ($user, $offer, $slotIds) {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $now = now();

            if (! $offer->wishesOpen($now)) {
                throw BookingException::wishesClosed();
            }

            if (Booking::query()->confirmed()->where('offer_id', $offer->id)->where('user_id', $user->id)->exists()) {
                throw BookingException::alreadyBooked();
            }

            $filled = array_values(array_filter(
                array_map(fn ($value) => $value === null || $value === '' ? null : (int) $value, $slotIds),
                fn ($value) => $value !== null,
            ));

            $freeIds = $offer->slots()
                ->where('starts_at', '>', $now)
                ->whereDoesntHave('bookings', fn ($booking) => $booking->confirmed())
                ->pluck('id')
                ->all();

            $min = min(2, count($freeIds));

            if (count($filled) < $min || count($filled) > self::MAX_WISHES || count(array_unique($filled)) !== count($filled)) {
                throw BookingException::wishCount($min);
            }

            if (array_keys(array_filter($slotIds, fn ($value) => $value !== null && $value !== '')) !== range(1, count($filled))) {
                throw BookingException::wishOrder();
            }

            foreach ($filled as $slotId) {
                if (! in_array($slotId, $freeIds, true)) {
                    throw BookingException::wishSlotUnavailable();
                }
            }

            WishEntry::query()->where('offer_id', $offer->id)->where('user_id', $user->id)->delete();

            foreach ($filled as $index => $slotId) {
                WishEntry::query()->create([
                    'offer_id' => $offer->id,
                    'user_id' => $user->id,
                    'slot_id' => $slotId,
                    'priority' => $index + 1,
                    'submitted_at' => $now,
                ]);
            }
        });
    }

    /**
     * Nimmt einen Vorschlag an. Der Termin wird dabei gebucht.
     */
    public function accept(User $user, WishAssignment $assignment): void
    {
        DB::transaction(function () use ($user, $assignment) {
            $assignment = WishAssignment::query()->with(['run', 'slot'])->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            Offer::query()->whereKey($assignment->offer_id)->lockForUpdate()->firstOrFail();

            $this->assertOpenProposal($user, $assignment);

            $this->bookings->book($user, $assignment->slot, BookingSource::Wish);

            $assignment->update(['alternative_response' => AlternativeResponse::Accepted]);
        });
    }

    public function decline(User $user, WishAssignment $assignment): void
    {
        DB::transaction(function () use ($user, $assignment) {
            $assignment = WishAssignment::query()->with('run')->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            $this->assertOpenProposal($user, $assignment);

            $assignment->update(['alternative_response' => AlternativeResponse::Declined]);
        });
    }

    private function assertOpenProposal(User $user, WishAssignment $assignment): void
    {
        if ($assignment->user_id !== $user->id) {
            throw BookingException::wishNotYours();
        }

        if (! $assignment->isPendingAlternative() || $assignment->run->status !== WishRunStatus::Released) {
            throw BookingException::alternativeNotPending();
        }
    }
}
