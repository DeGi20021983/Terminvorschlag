@extends('layouts.app')

@section('title', 'Teilnehmerliste')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Teilnehmerliste</h1>
        <p>Die aktuelle Belegung der Termine Ihrer Angebote.</p>
    </header>

    <form method="get" action="{{ route('manage.participants.index') }}" class="filters">
        <div class="field">
            <label for="offer">Angebot</label>
            <select id="offer" name="offer">
                @foreach ($offers as $offer)
                    <option value="{{ $offer->id }}" @selected($selected?->id === $offer->id)>{{ $offer->title }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn--secondary" type="submit">Anzeigen</button>
    </form>

    @if ($selected)
        <p class="status-line" role="status" aria-live="polite">
            Stand: {{ $updatedAt->format('H:i') }} Uhr
        </p>

        <div class="table-wrap" class="table-wrap table-wrap--spaced">
            <table class="table">
                <caption>Termine von {{ $selected->title }}</caption>
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
                                          data-confirm="Diese Person wirklich austragen?">
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
        </div>
    @else
        <p class="card empty-state">Sie haben noch keine Angebote.</p>
    @endif
@endsection
