<?php

namespace App\Services;

use App\Enums\AllocationMode;
use App\Enums\AlternativeResponse;
use App\Enums\BookingSource;
use App\Enums\WishOutcome;
use App\Enums\WishRunStatus;
use App\Events\WishesReleased;
use App\Exceptions\BookingException;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishAssignment;
use App\Models\WishDraw;
use App\Models\WishEntry;
use App\Models\WishRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verteilt Wunschtermine nach Ablauf der Frist.
 *
 * Reihenfolge: zuerst alle Erstwünsche, dann Zweit- und Drittwünsche. Wer in einer Runde
 * den Zuschlag bekommt, wird protokolliert. Bei Gleichstand um einen Termin entscheidet
 * ein Los (random_int). Wer leer ausgeht, bekommt den frühesten noch freien Termin als
 * Vorschlag. Er kann ihn annehmen oder ablehnen.
 */
final class WishDistributionService
{
    private const ROUNDS = 3;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BookingService $bookings,
    ) {}

    /**
     * Berechnet die Verteilung neu. Eine vorherige, noch nicht freigegebene Berechnung wird ersetzt.
     */
    public function calculate(User $organizer, Offer $offer): WishRun
    {
        return DB::transaction(function () use ($organizer, $offer) {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $now = now();

            $this->assertWishOffer($offer);

            if ($offer->wishes_released_at !== null) {
                throw BookingException::wishesAlreadyReleased();
            }

            if ($offer->wish_deadline === null || $now->lessThan($offer->wish_deadline)) {
                throw BookingException::wishDeadlineNotReached();
            }

            $offer->wishRuns()->where('status', WishRunStatus::Proposed)
                ->update(['status' => WishRunStatus::Superseded]);

            $run = WishRun::query()->create([
                'offer_id' => $offer->id,
                'status' => WishRunStatus::Proposed,
                'calculated_by_user_id' => $organizer->id,
                'calculated_at' => $now,
            ]);

            $freeSlots = $offer->slots()
                ->where('starts_at', '>', $now)
                ->whereDoesntHave('bookings', fn ($booking) => $booking->confirmed())
                ->orderBy('starts_at')
                ->get();

            $freeIds = $freeSlots->pluck('id')->flip();

            $bookedUserIds = $offer->bookings()->confirmed()->pluck('user_id')->filter()->all();

            $wishesByUser = WishEntry::query()
                ->where('offer_id', $offer->id)
                ->whereNotNull('user_id')
                ->whereIn('slot_id', $freeIds->keys())
                ->orderBy('submitted_at')
                ->orderBy('priority')
                ->get()
                ->groupBy('user_id')
                ->reject(fn ($wishes, $userId) => in_array($userId, $bookedUserIds, false));

            /** @var array<int, array{slot: ?Slot, outcome: WishOutcome, byLot: bool, response: ?AlternativeResponse}> $assignments */
            $assignments = [];
            $taken = [];

            for ($round = 1; $round <= self::ROUNDS; $round++) {
                $candidates = $this->candidatesForRound($round, $wishesByUser, $assignments, $taken);

                foreach ($freeSlots as $slot) {
                    $bySlot = $candidates[$slot->id] ?? [];

                    if ($bySlot === [] || isset($taken[$slot->id])) {
                        continue;
                    }

                    $byLot = count($bySlot) > 1;
                    $winner = $byLot ? $bySlot[random_int(0, count($bySlot) - 1)] : $bySlot[0];

                    if ($byLot) {
                        WishDraw::query()->create([
                            'wish_run_id' => $run->id,
                            'slot_id' => $slot->id,
                            'round' => $round,
                            'candidate_user_ids' => $bySlot,
                            'winner_user_id' => $winner,
                        ]);
                    }

                    $assignments[$winner] = [
                        'slot' => $slot,
                        'outcome' => WishOutcome::from('wish'.$round),
                        'byLot' => $byLot,
                        'response' => null,
                    ];
                    $taken[$slot->id] = true;
                }
            }

            $remaining = $freeSlots->reject(fn (Slot $slot) => isset($taken[$slot->id]))->values();

            foreach ($wishesByUser->sortBy(fn (Collection $wishes) => $wishes->min('submitted_at')) as $userId => $wishes) {
                if (isset($assignments[$userId])) {
                    continue;
                }

                $slot = $remaining->shift();

                $assignments[$userId] = $slot === null
                    ? ['slot' => null, 'outcome' => WishOutcome::None, 'byLot' => false, 'response' => null]
                    : ['slot' => $slot, 'outcome' => WishOutcome::Alternative, 'byLot' => false, 'response' => AlternativeResponse::Pending];
            }

            foreach ($assignments as $userId => $data) {
                WishAssignment::query()->create([
                    'wish_run_id' => $run->id,
                    'offer_id' => $offer->id,
                    'user_id' => $userId,
                    'slot_id' => $data['slot']?->id,
                    'outcome' => $data['outcome'],
                    'by_lot' => $data['byLot'],
                    'alternative_response' => $data['response'],
                ]);
            }

            $this->audit->record($organizer, 'wishes.calculated', $run, $offer, [
                'persons' => count($assignments),
                'lots' => $run->draws()->count(),
            ]);

            return $run;
        });
    }

    /**
     * Ändert den vorgeschlagenen Termin einer Zuteilung, solange die Verteilung nicht freigegeben ist.
     */
    public function changeAssignment(User $organizer, WishAssignment $assignment, Slot $slot): WishAssignment
    {
        return DB::transaction(function () use ($organizer, $assignment, $slot) {
            $assignment = WishAssignment::query()->with('run')->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $offer = Offer::query()->whereKey($assignment->offer_id)->lockForUpdate()->firstOrFail();
            $now = now();

            if ($assignment->run->status !== WishRunStatus::Proposed) {
                throw BookingException::wishesAlreadyReleased();
            }

            if ($slot->offer_id !== $offer->id) {
                throw BookingException::slotNotInOffer();
            }

            if ($slot->starts_at->lessThanOrEqualTo($now)) {
                throw BookingException::slotStarted();
            }

            if ($slot->bookings()->confirmed()->exists()) {
                throw BookingException::slotTaken();
            }

            $inUse = WishAssignment::query()
                ->where('wish_run_id', $assignment->wish_run_id)
                ->where('slot_id', $slot->id)
                ->whereKeyNot($assignment->id)
                ->exists();

            if ($inUse) {
                throw BookingException::slotTaken();
            }

            $changes = ['slot_id' => $slot->id];

            // Ohne Zuteilung wird der Termin eine direkte Zuteilung der Alternative.
            if ($assignment->outcome === WishOutcome::None) {
                $changes['outcome'] = WishOutcome::Alternative;
                $changes['alternative_response'] = null;
            }

            $assignment->update($changes);

            $this->audit->record($organizer, 'wishes.assignment_changed', $assignment, $offer, [
                'slot_id' => $slot->id,
            ]);

            return $assignment;
        });
    }

    /**
     * Gibt die Verteilung frei: direkte Zuteilungen werden gebucht, Vorschläge warten auf die Antwort.
     */
    public function release(User $organizer, Offer $offer): WishRun
    {
        $result = DB::transaction(function () use ($organizer, $offer) {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            $this->assertWishOffer($offer);

            if ($offer->wishes_released_at !== null) {
                throw BookingException::wishesAlreadyReleased();
            }

            $run = $offer->wishRuns()->where('status', WishRunStatus::Proposed)->latest('id')->first();

            if ($run === null) {
                throw BookingException::noWishRun();
            }

            $assignments = $run->assignments()->with(['user', 'slot'])->get();

            $direct = $assignments->filter(fn (WishAssignment $item) => $item->isDirect() && $item->slot !== null);

            foreach ($direct as $item) {
                if ($item->user !== null) {
                    $this->bookings->book($item->user, $item->slot, BookingSource::Wish, $organizer);
                }
            }

            $run->update([
                'status' => WishRunStatus::Released,
                'released_at' => now(),
                'released_by_user_id' => $organizer->id,
            ]);

            $offer->update(['wishes_released_at' => now()]);

            $this->audit->record($organizer, 'wishes.released', $run, $offer, [
                'direct' => $direct->count(),
                'proposals' => $assignments->filter(fn (WishAssignment $item) => $item->isPendingAlternative())->count(),
                'without_slot' => $assignments->filter(fn (WishAssignment $item) => $item->outcome === WishOutcome::None)->count(),
            ]);

            return [$offer, $run];
        });

        [$offer, $run] = $result;

        event(new WishesReleased($offer, $run));

        return $run;
    }

    /**
     * Personen, die in dieser Runde noch keinen Platz haben, mit den Ergebnissen der bisherigen Runden.
     *
     * @param  Collection<int, Collection<int, WishEntry>>  $wishesByUser
     * @param  array<int, array<string, mixed>>  $assignments
     * @param  array<int, bool>  $taken
     * @return array<int, list<int>> Termin-ID => Personen-IDs in Reihenfolge
     */
    private function candidatesForRound(int $round, Collection $wishesByUser, array $assignments, array $taken): array
    {
        $candidates = [];

        foreach ($wishesByUser as $userId => $wishes) {
            if (isset($assignments[$userId])) {
                continue;
            }

            $wish = $wishes->firstWhere('priority', $round);

            if ($wish === null || isset($taken[$wish->slot_id])) {
                continue;
            }

            $candidates[$wish->slot_id][] = (int) $userId;
        }

        return $candidates;
    }

    private function assertWishOffer(Offer $offer): void
    {
        if ($offer->allocation_mode !== AllocationMode::Wishes) {
            throw BookingException::notWishOffer();
        }
    }
}
