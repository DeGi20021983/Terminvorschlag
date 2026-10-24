<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Offer;
use App\Models\WishAssignment;
use App\Services\WishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wünsche abgeben, Vorschläge annehmen oder ablehnen (Mitarbeitende).
 */
class WishSubmissionController extends Controller
{
    public function __construct(
        private readonly WishService $wishes,
    ) {}

    public function store(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('view', $offer);

        $request->validate([
            'wishes' => ['required', 'array'],
            'wishes.*' => ['nullable', 'integer'],
        ]);

        try {
            $this->wishes->submit($request->user(), $offer, $request->input('wishes', []));
        } catch (BookingException $exception) {
            return back()->withErrors(['wish' => $exception->getMessage()]);
        }

        return back()->with('status', 'Ihre Wunschtermine sind gespeichert.');
    }

    public function accept(Request $request, WishAssignment $assignment): RedirectResponse
    {
        try {
            $this->wishes->accept($request->user(), $assignment);
        } catch (BookingException $exception) {
            return redirect()->route('bookings.mine')->withErrors(['wish' => $exception->getMessage()]);
        }

        return redirect()->route('bookings.mine')->with('status', 'Der Vorschlag ist angenommen und Ihr Termin gebucht.');
    }

    public function decline(Request $request, WishAssignment $assignment): RedirectResponse
    {
        try {
            $this->wishes->decline($request->user(), $assignment);
        } catch (BookingException $exception) {
            return redirect()->route('bookings.mine')->withErrors(['wish' => $exception->getMessage()]);
        }

        return redirect()->route('bookings.mine')->with('status', 'Der Vorschlag wurde abgelehnt.');
    }
}
