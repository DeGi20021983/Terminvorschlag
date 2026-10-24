<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\OfferStatus;
use App\Models\Booking;
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

        return view('bookings.mine', compact('upcoming', 'past', 'wishes', 'now'));
    }
}
