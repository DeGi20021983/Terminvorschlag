<?php

namespace App\Http\Controllers\Manage;

use App\Enums\WishOutcome;
use App\Enums\WishRunStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\WishAssignment;
use App\Models\WishEntry;
use App\Services\WishDistributionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wunschverteilung für die eigenen Angebote: Ansicht, Berechnung, Änderung und Freigabe.
 */
class WishController extends Controller
{
    public function __construct(
        private readonly WishDistributionService $distribution,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('create', Offer::class);

        $offers = Offer::query()
            ->where('owner_user_id', $request->user()->id)
            ->where('allocation_mode', 'wishes')
            ->orderByDesc('bookable_from')
            ->get();

        $selected = $offers->firstWhere('id', $request->integer('offer')) ?? $offers->first();

        if ($selected === null) {
            return view('manage.wishes.index', ['offers' => $offers, 'selected' => null]);
        }

        $now = now();

        $run = $selected->wishRuns()
            ->whereIn('status', [WishRunStatus::Proposed, WishRunStatus::Released])
            ->latest('id')
            ->first();

        $freeSlots = $selected->slots()
            ->where('starts_at', '>', $now)
            ->whereDoesntHave('bookings', fn ($booking) => $booking->confirmed())
            ->orderBy('starts_at')
            ->get();

        $assignments = $run === null
            ? collect()
            : $run->assignments()->with(['user', 'slot'])->get()->keyBy('user_id');

        $wishes = WishEntry::query()
            ->where('offer_id', $selected->id)
            ->whereNotNull('user_id')
            ->with('slot')
            ->orderBy('priority')
            ->get()
            ->groupBy('user_id');

        $rows = $wishes->keys()
            ->merge($assignments->keys())
            ->unique()
            ->map(fn ($userId) => [
                'user' => $assignments->get($userId)?->user ?? $wishes->get($userId)?->first()?->user,
                'wishes' => $wishes->get($userId, collect())->keyBy('priority'),
                'assignment' => $assignments->get($userId),
            ])
            ->filter(fn (array $row) => $row['user'] !== null)
            ->sortBy(fn (array $row) => $row['user']->display_name ?? $row['user']->username)
            ->values();

        // Welcher Termin ist bereits vergeben, damit man ihn keiner zweiten Person zuweist.
        $usedBy = $assignments->filter(fn (WishAssignment $item) => $item->slot_id !== null)
            ->mapWithKeys(fn (WishAssignment $item) => [$item->slot_id => $item->user_id]);

        $kpis = [
            'persons' => $rows->count(),
            'first' => $assignments->filter(fn (WishAssignment $item) => $item->outcome === WishOutcome::Wish1)->count(),
            'lots' => $assignments->filter(fn (WishAssignment $item) => $item->by_lot)->count(),
            'alternative' => $assignments->filter(fn (WishAssignment $item) => $item->outcome === WishOutcome::Alternative)->count(),
            'none' => $assignments->filter(fn (WishAssignment $item) => $item->outcome === WishOutcome::None)->count(),
        ];

        $draws = $run === null
            ? collect()
            : $run->draws()->with(['slot', 'winner'])->orderBy('round')->orderBy('id')->get();

        return view('manage.wishes.index', [
            'offers' => $offers,
            'selected' => $selected,
            'run' => $run,
            'rows' => $rows,
            'kpis' => $kpis,
            'draws' => $draws,
            'freeSlots' => $freeSlots,
            'usedBy' => $usedBy,
            'canCalculate' => $selected->wish_deadline !== null
                && $now->greaterThanOrEqualTo($selected->wish_deadline)
                && $selected->wishes_released_at === null,
            'canRelease' => $run !== null && $run->status === WishRunStatus::Proposed,
        ]);
    }

    public function calculate(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        try {
            $this->distribution->calculate($request->user(), $offer);
        } catch (BookingException $exception) {
            return back()->withErrors(['wish' => $exception->getMessage()]);
        }

        return redirect()->route('manage.wishes.index', ['offer' => $offer->id])
            ->with('status', 'Die Verteilung ist berechnet. Prüfen Sie die Vorschläge, bevor Sie freigeben.');
    }

    public function release(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('manage', $offer);

        try {
            $this->distribution->release($request->user(), $offer);
        } catch (BookingException $exception) {
            return back()->withErrors(['wish' => $exception->getMessage()]);
        }

        return redirect()->route('manage.wishes.index', ['offer' => $offer->id])
            ->with('status', 'Die Verteilung ist freigegeben. Die Personen werden benachrichtigt.');
    }

    public function update(Request $request, WishAssignment $assignment): RedirectResponse
    {
        $this->authorize('manage', $assignment->offer);

        $validated = $request->validate(['slot_id' => ['required', 'integer', 'exists:slots,id']]);

        try {
            $this->distribution->changeAssignment(
                $request->user(),
                $assignment,
                Slot::query()->findOrFail($validated['slot_id']),
            );
        } catch (BookingException $exception) {
            return back()->withErrors(['wish' => $exception->getMessage()]);
        }

        return back()->with('status', 'Der Vorschlag wurde geändert.');
    }
}
