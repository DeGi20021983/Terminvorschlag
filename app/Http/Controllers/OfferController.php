<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Http\Requests\OfferRequest;
use App\Models\Offer;
use App\Services\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferService $offers,
    ) {}

    public function store(OfferRequest $request): RedirectResponse
    {
        $this->authorize('create', Offer::class);

        $offer = $this->offers->create($request->user(), $request->offerData());

        return redirect()->route('manage.offers.edit', $offer)
            ->with('status', 'Angebot angelegt. Fügen Sie nun die Termine hinzu.');
    }

    public function update(OfferRequest $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        $this->offers->update($request->user(), $offer, $request->offerData());

        return redirect()->route('manage.offers.edit', $offer)->with('status', 'Angebot gespeichert.');
    }

    public function publish(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        try {
            $this->offers->publish($request->user(), $offer);
        } catch (BookingException $exception) {
            return redirect()->route('manage.offers.edit', $offer)->withErrors(['offer' => $exception->getMessage()]);
        }

        return redirect()->route('manage.offers.edit', $offer)->with('status', 'Angebot veröffentlicht.');
    }

    public function cancel(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->offers->cancel($request->user(), $offer, $request->input('note'));
        } catch (BookingException $exception) {
            return redirect()->route('manage.offers.edit', $offer)->withErrors(['offer' => $exception->getMessage()]);
        }

        return redirect()->route('manage.offers.edit', $offer)
            ->with('status', 'Angebot abgesagt. Die gebuchten Personen werden benachrichtigt.');
    }
}
