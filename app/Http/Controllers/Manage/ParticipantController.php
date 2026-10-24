<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Teilnehmerliste: nur die Angebote der eigenen Organisation.
 */
class ParticipantController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', Offer::class);

        $offers = Offer::query()
            ->where('owner_user_id', $request->user()->id)
            ->orderByDesc('bookable_from')
            ->get();

        $selected = $offers->firstWhere('id', $request->integer('offer')) ?? $offers->first();

        $slots = $selected === null
            ? collect()
            : $selected->slots()->with('activeBooking.user')->orderBy('starts_at')->get();

        return view('manage.participants.index', [
            'offers' => $offers,
            'selected' => $selected,
            'slots' => $slots,
            'updatedAt' => now(),
        ]);
    }
}
