<?php

namespace App\Http\Controllers;

use App\Enums\AllocationMode;
use App\Enums\OfferStatus;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\WishEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Angebotsübersicht und Buchungsseite für Mitarbeitende.
 */
class OfferCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $now = now();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'vergabe' => (string) $request->query('vergabe', ''),
            'bereich' => (string) $request->query('bereich', ''),
            'frei' => $request->boolean('frei'),
        ];

        $offers = Offer::query()
            ->where('status', OfferStatus::Published)
            ->where('bookable_until', '>=', $now)
            ->with('location')
            ->withCount([
                'slots',
                'slots as free_slots_count' => fn (Builder $query) => $query
                    ->where('starts_at', '>', $now)
                    ->whereDoesntHave('bookings', fn (Builder $booking) => $booking->confirmed()),
            ])
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$filters['q'].'%')
                ->orWhere('description', 'like', '%'.$filters['q'].'%')
                ->orWhere('area', 'like', '%'.$filters['q'].'%')))
            ->when(AllocationMode::tryFrom($filters['vergabe']) !== null, fn (Builder $query) => $query
                ->where('allocation_mode', $filters['vergabe']))
            ->when($filters['bereich'] !== '', fn (Builder $query) => $query->where('area', $filters['bereich']))
            ->when($filters['frei'], fn (Builder $query) => $query->having('free_slots_count', '>', 0))
            ->orderBy('bookable_until')
            ->orderBy('title')
            ->get();

        $areas = Offer::query()
            ->where('status', OfferStatus::Published)
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return view('offers.index', compact('offers', 'filters', 'areas'));
    }

    public function show(Request $request, Offer $offer): View
    {
        $this->authorize('view', $offer);

        $now = now();
        $user = $request->user();

        $slots = $offer->slots()->with('activeBooking')->orderBy('starts_at')->get();

        $bookable = $offer->isInBookingWindow($now) && $offer->instantBookingOpen();

        $ownBooking = Booking::query()->confirmed()
            ->where('offer_id', $offer->id)
            ->where('user_id', $user->id)
            ->with('slot')
            ->first();

        $selected = null;

        if ($bookable && $request->filled('termin')) {
            $candidate = $slots->firstWhere('id', $request->integer('termin'));

            if ($candidate !== null && $this->isFree($candidate, $now)) {
                $selected = $candidate;
            }
        }

        $wishesOpen = $offer->wishesOpen($now);

        $wishSlots = $slots->filter(fn ($termin) => $termin->activeBooking === null && $termin->starts_at->greaterThan($now))->values();

        $myWishes = $wishesOpen
            ? WishEntry::query()->where('offer_id', $offer->id)->where('user_id', $user->id)->get()->keyBy('priority')
            : collect();

        return view('offers.show', [
            'offer' => $offer,
            'slots' => $slots,
            'now' => $now,
            'bookable' => $bookable,
            'ownBooking' => $ownBooking,
            'selected' => $selected,
            'userId' => $user->id,
            'wishesOpen' => $wishesOpen,
            'wishSlots' => $wishSlots,
            'myWishes' => $myWishes,
        ]);
    }

    private function isFree($slot, $now): bool
    {
        return $slot->activeBooking === null && $slot->starts_at->greaterThan($now);
    }
}
