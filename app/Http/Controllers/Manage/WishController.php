<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Wunschverteilung für die eigenen Angebote mit Wunschterminen.
 */
class WishController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', Offer::class);

        $offers = Offer::query()
            ->where('owner_user_id', $request->user()->id)
            ->where('allocation_mode', 'wishes')
            ->orderByDesc('bookable_from')
            ->get();

        $selected = $offers->firstWhere('id', $request->integer('offer')) ?? $offers->first();

        return view('manage.wishes.index', compact('offers', 'selected'));
    }
}
