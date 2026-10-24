{{-- Tabelle der Teilnehmerliste. Wird beim Laden und bei jeder Aktualisierung gerendert. --}}
<table class="table">
    <caption>Termine von {{ $offer->title }}</caption>
    <thead>
        <tr>
            <th scope="col">Termin</th>
            <th scope="col">Status</th>
            <th scope="col">Person</th>
            <th scope="col">Organisationseinheit</th>
            <th scope="col"><span class="sr-only">Aktion</span></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($slots as $termin)
            @php($buchung = $termin->activeBooking)
            <tr>
                <td>{{ $termin->dateLabel() }}, {{ $termin->timeRange() }}</td>
                <td>
                    @if ($buchung)
                        <x-badge variant="booked">Belegt</x-badge>
                    @else
                        <x-badge variant="free">Frei</x-badge>
                    @endif
                </td>
                <td>{{ $buchung?->user?->name() ?? '–' }}</td>
                <td>{{ $buchung?->user?->department ?? '–' }}</td>
                <td>
                    @if ($buchung)
                        <form method="post" action="{{ route('manage.bookings.remove', $buchung) }}" class="inline-form"
                              data-confirm="Diese Person wirklich austragen? Der Termin wird danach wieder frei.">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn--small" type="submit">Austragen<span class="sr-only"> von {{ $termin->dateLabel() }}</span></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">Für dieses Angebot sind noch keine Termine angelegt.</td></tr>
        @endforelse
    </tbody>
</table>
