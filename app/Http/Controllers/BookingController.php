<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Http\Requests\MoveBookingRequest;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * Sofortbuchung eines Termins. Der Platz ist ab der Bestätigung gesperrt.
     */
    public function store(Request $request, Offer $offer, Slot $slot): RedirectResponse
    {
        $this->authorize('view', $offer);

        if ($slot->offer_id !== $offer->id) {
            return back()->withErrors(['booking' => BookingException::slotNotInOffer()->getMessage()]);
        }

        return $this->attempt(fn () => $this->bookings->book($request->user(), $slot), 'Ihr Termin ist gebucht.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        return $this->attempt(fn () => $this->bookings->cancel($request->user(), $booking), 'Ihr Termin wurde storniert.');
    }

    /**
     * Umbuchen auf einen anderen freien Termin desselben Angebots, in einem Schritt.
     */
    public function move(MoveBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        $newSlot = Slot::query()->findOrFail($request->integer('slot_id'));

        return $this->attempt(
            fn () => $this->bookings->move($request->user(), $booking, $newSlot),
            'Ihr Termin wurde umgebucht.',
        );
    }

    /**
     * Austragen durch den Organisator.
     */
    public function remove(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking->offer);

        return $this->attempt(
            fn () => $this->bookings->cancelByOrganizer($booking),
            'Die Person wurde ausgetragen.',
        );
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (BookingException $exception) {
            return back()->withErrors(['booking' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
