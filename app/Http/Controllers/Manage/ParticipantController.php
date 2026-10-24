<?php

namespace App\Http\Controllers\Manage;

use App\Directory\DirectoryUnavailableException;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Slot;
use App\Services\ParticipantExport;
use App\Services\ParticipantService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teilnehmerliste der eigenen Angebote: Ansicht, Live-Aktualisierung, Exporte,
 * Anwesenheitsliste, manuelles Eintragen und Nachrichten.
 */
class ParticipantController extends Controller
{
    public function __construct(
        private readonly ParticipantService $participants,
        private readonly ParticipantExport $export,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('create', Offer::class);

        $offers = Offer::query()
            ->where('owner_user_id', $request->user()->id)
            ->orderByDesc('bookable_from')
            ->get();

        $selected = $offers->firstWhere('id', $request->integer('offer')) ?? $offers->first();

        if ($selected === null) {
            return view('manage.participants.index', ['offers' => $offers, 'selected' => null]);
        }

        $this->authorize('viewParticipants', $selected);

        return view('manage.participants.index', [
            'offers' => $offers,
            'selected' => $selected,
            'slots' => $this->slotsFor($selected),
            'updatedAt' => now(),
        ]);
    }

    /**
     * Tabellenteil für die Live-Aktualisierung (Polling aus dem Browser).
     */
    public function live(Offer $offer): Response
    {
        $this->authorize('viewParticipants', $offer);

        return response()
            ->view('manage.participants._table', ['offer' => $offer, 'slots' => $this->slotsFor($offer)])
            ->header('X-Stand', now()->format('H:i'));
    }

    public function csv(Offer $offer): StreamedResponse
    {
        $this->authorize('viewParticipants', $offer);

        $rows = $this->export->rows($offer);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM, damit Excel die Umlaute richtig liest. Semikolon als Trennzeichen für deutsche Einstellungen.
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $this->fileName($offer, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(Offer $offer): StreamedResponse
    {
        $this->authorize('viewParticipants', $offer);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Teilnehmer');
        $sheet->fromArray($this->export->rows($offer), null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $this->fileName($offer, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function print(Offer $offer): View
    {
        $this->authorize('viewParticipants', $offer);

        return view('manage.participants.print', [
            'offer' => $offer,
            'slots' => $this->slotsFor($offer),
            'printedAt' => now(),
        ]);
    }

    public function add(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('viewParticipants', $offer);

        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $slot = Slot::query()->where('offer_id', $offer->id)->findOrFail($validated['slot_id']);

        try {
            $this->participants->addPerson($request->user(), $offer, $slot, $validated['username']);
        } catch (BookingException $exception) {
            return back()->withErrors(['participant' => $exception->getMessage()]);
        } catch (DirectoryUnavailableException) {
            return back()->withErrors(['participant' => 'Das Verzeichnis ist gerade nicht erreichbar.']);
        }

        return back()->with('status', 'Die Person ist eingetragen.');
    }

    public function message(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('viewParticipants', $offer);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $count = $this->participants->message($request->user(), $offer, $validated['subject'], $validated['body']);
        } catch (BookingException $exception) {
            return back()->withErrors(['message' => $exception->getMessage()]);
        }

        return back()->with('status', "Die Nachricht wurde an {$count} Personen eingereiht.");
    }

    /**
     * @return Collection<int, Slot>
     */
    private function slotsFor(Offer $offer)
    {
        return $offer->slots()->with('activeBooking.user')->orderBy('starts_at')->get();
    }

    private function fileName(Offer $offer, string $extension): string
    {
        return 'teilnehmer-'.Str::slug($offer->title).'.'.$extension;
    }
}
