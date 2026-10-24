<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Offer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Bildschirme „Angebote verwalten“ (Liste, Neu, Bearbeiten). Schreibvorgänge liegen in den Controllern unter App\Http\Controllers.
 */
class OfferPageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', Offer::class);

        $offers = Offer::query()
            ->where('owner_user_id', $request->user()->id)
            ->with('location')
            ->withCount('slots')
            ->orderByDesc('bookable_from')
            ->get();

        return view('manage.offers.index', compact('offers'));
    }

    public function create(): View
    {
        $this->authorize('create', Offer::class);

        return view('manage.offers.create', [
            'locations' => $this->locations(),
        ]);
    }

    public function edit(Offer $offer): View
    {
        $this->authorize('manage', $offer);

        $slots = $offer->slots()->with('activeBooking.user')->orderBy('starts_at')->get();

        return view('manage.offers.edit', [
            'offer' => $offer,
            'slots' => $slots,
            'locations' => $this->locations(),
        ]);
    }

    /**
     * @return Collection<int, Location>
     */
    private function locations()
    {
        return Location::query()->where('is_active', true)->orderBy('name')->get();
    }
}
