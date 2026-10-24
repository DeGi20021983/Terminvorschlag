<?php

namespace App\Http\Controllers;

use App\Enums\AlternativeResponse;
use App\Enums\BookingStatus;
use App\Enums\OfferStatus;
use App\Enums\WishOutcome;
use App\Enums\WishRunStatus;
use App\Models\Booking;
use App\Models\WishAssignment;
use App\Models\WishEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * „Meine Termine“: alle eigenen Buchungen mit Status und offene Wünsche.
 */
class MyBookingsController extends Controller
{
    public function index(Request $request): View
    {
        $now = now();
        $userId = $request->user()->id;

        $bookings = Booking::query()
            ->where('user_id', $userId)
            ->with(['offer.location', 'slot'])
            ->get()
            ->sortBy(fn (Booking $booking) => $booking->slot->starts_at);

        $upcoming = $bookings->filter(fn (Booking $booking) => $booking->status === BookingStatus::Confirmed
            && $booking->slot->starts_at->greaterThan($now))->values();

        $past = $bookings->reject(fn (Booking $booking) => $booking->status === BookingStatus::Confirmed
            && $booking->slot->starts_at->greaterThan($now))->sortByDesc(fn (Booking $booking) => $booking->slot->starts_at)->values();

        $wishes = WishEntry::query()
            ->where('user_id', $userId)
            ->whereHas('offer', fn ($query) => $query->whereNull('wishes_released_at')->where('status', '!=', OfferStatus::Cancelled))
            ->with(['offer', 'slot'])
            ->orderBy('priority')
            ->get();

        $proposals = WishAssignment::query()
            ->where('user_id', $userId)
            ->where('alternative_response', AlternativeResponse::Pending)
            ->whereHas('run', fn ($run) => $run->where('status', WishRunStatus::Released))
            ->with(['offer.location', 'slot'])
            ->get()
            ->sortBy(fn (WishAssignment $item) => $item->slot->starts_at)
            ->values();

        $withoutSlot = WishAssignment::query()
            ->where('user_id', $userId)
            ->where('outcome', WishOutcome::None)
            ->whereHas('run', fn ($run) => $run->where('status', WishRunStatus::Released))
            ->with('offer')
            ->get();

        return view('bookings.mine', compact('upcoming', 'past', 'wishes', 'proposals', 'withoutSlot', 'now'));
    }
}
