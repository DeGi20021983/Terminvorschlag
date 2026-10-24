@extends('layouts.plain')

@section('title', 'Anwesenheitsliste')

@section('content')
    <div class="print-sheet">
        <div class="toolbar no-print">
            <button class="btn btn--primary" type="button" data-print>Drucken</button>
            <a class="btn btn--secondary" href="{{ route('manage.participants.index', ['offer' => $offer->id]) }}">Zurück zur Teilnehmerliste</a>
        </div>

        <h1>Anwesenheitsliste: {{ $offer->title }}</h1>
        <p class="meta-line">Ansprechperson: {{ $offer->contact_name }} · {{ $offer->locationLabel() }} · gedruckt am {{ $printedAt->translatedFormat('d.m.Y') }} um {{ $printedAt->format('H:i') }} Uhr</p>

        <div class="table-wrap">
        <table class="table print-table">
            <caption class="sr-only">Anwesenheit je Termin</caption>
            <thead>
                <tr>
                    <th scope="col">Termin</th>
                    <th scope="col">Name</th>
                    <th scope="col">Organisationseinheit</th>
                    <th scope="col">Anwesend</th>
                    <th scope="col">Unterschrift</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($slots->filter(fn ($termin) => $termin->activeBooking !== null) as $termin)
                    <tr>
                        <td>{{ $termin->dateLabel() }}, {{ $termin->timeRange() }}</td>
                        <td>{{ $termin->activeBooking->user?->name() ?? '–' }}</td>
                        <td>{{ $termin->activeBooking->user?->department ?? '–' }}</td>
                        <td class="print-box" aria-label="Anwesend, bitte ankreuzen"></td>
                        <td class="print-sign"></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Noch keine Personen eingetragen.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
@endsection
