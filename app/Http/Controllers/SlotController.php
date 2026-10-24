<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Http\Requests\CopySlotRequest;
use App\Http\Requests\GridRequest;
use App\Http\Requests\SlotRequest;
use App\Models\Offer;
use App\Models\Slot;
use App\Services\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function __construct(
        private readonly OfferService $offers,
    ) {}

    public function store(SlotRequest $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        return $this->attempt(fn () => $this->offers->addSlot(
            $request->user(), $offer, $request->startsAt(), $request->endsAt(),
        ), 'Termin angelegt.');
    }

    public function grid(GridRequest $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        return $this->attempt(fn () => $this->offers->addGrid(
            $request->user(),
            $offer,
            $request->day(),
            $request->input('from'),
            $request->input('to'),
            (int) $request->input('minutes'),
        ), 'Zeitraster angelegt.');
    }

    public function copy(CopySlotRequest $request, Slot $slot): RedirectResponse
    {
        $this->authorize('manage', $slot->offer);

        return $this->attempt(fn () => $this->offers->copySlot(
            $request->user(), $slot, $request->startsAt(),
        ), 'Termin kopiert.');
    }

    public function destroy(Request $request, Slot $slot): RedirectResponse
    {
        $this->authorize('manage', $slot->offer);

        return $this->attempt(function () use ($request, $slot) {
            $this->offers->deleteSlot($request->user(), $slot);
        }, 'Termin gelöscht.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (BookingException $exception) {
            return back()->withErrors(['slot' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
