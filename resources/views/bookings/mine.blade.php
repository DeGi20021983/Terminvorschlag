@extends('layouts.app')

@section('title', 'Meine Termine')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Meine Termine</h1>
        <p>Ihre Buchungen und Wünsche auf einen Blick.</p>
    </header>

    <h2 class="section-title">Kommende Termine</h2>
    <p class="muted">Beim Umbuchen bleibt der alte Platz bis zur Bestätigung des neuen Termins gebucht.</p>
    <div class="booking-stack">
        @forelse ($upcoming as $booking)
            @include('bookings._card', ['booking' => $booking, 'past' => false])
        @empty
            <p class="card empty-state">Sie haben keine kommenden Termine.</p>
        @endforelse
    </div>

    @if ($proposals->isNotEmpty())
        <h2 class="section-title">Vorschläge</h2>
        <p class="muted">Sie haben keinen Ihrer Wunschtermine erhalten. Diesen freien Termin können Sie annehmen oder ablehnen.</p>
        <div class="booking-stack">
            @foreach ($proposals as $proposal)
                <article class="booking-card" aria-labelledby="vorschlag-{{ $proposal->id }}">
                    <div class="date-block" aria-hidden="true">
                        <span class="date-block__weekday">{{ $proposal->slot->starts_at->translatedFormat('D') }}</span>
                        <span class="date-block__day">{{ $proposal->slot->starts_at->format('d') }}</span>
                        <span class="date-block__time">{{ $proposal->slot->starts_at->format('H:i') }}</span>
                    </div>
                    <div>
                        <div class="booking-card__head">
                            <h2 id="vorschlag-{{ $proposal->id }}">{{ $proposal->offer->title }}</h2>
                            <x-badge variant="hint">Alternative vorgeschlagen</x-badge>
                        </div>
                        <p class="muted">{{ $proposal->slot->dateLabel() }}, {{ $proposal->slot->timeRange() }} · {{ $proposal->offer->locationLabel() }}</p>
                        <div class="booking-card__actions">
                            <form method="post" action="{{ route('wishes.accept', $proposal) }}" class="inline-form">
                                @csrf
                                <button class="btn btn--small btn--primary" type="submit">Annehmen</button>
                            </form>
                            <form method="post" action="{{ route('wishes.decline', $proposal) }}" class="inline-form">
                                @csrf
                                <button class="btn btn--small" type="submit">Ablehnen</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($withoutSlot->isNotEmpty())
        <h2 class="section-title">Ohne Zuteilung</h2>
        <div class="booking-stack">
            @foreach ($withoutSlot as $item)
                <p class="card">{{ $item->offer->title }}: Für Ihre Wünsche war kein Termin mehr frei. Sie können noch freie Termine direkt buchen.</p>
            @endforeach
        </div>
    @endif

    @if ($wishes->isNotEmpty())
        <h2 class="section-title">Wünsche</h2>
        <div class="booking-stack">
            @foreach ($wishes as $wish)
                <article class="booking-card" aria-labelledby="wunsch-{{ $wish->id }}">
                    <div class="date-block" aria-hidden="true">
                        <span class="date-block__weekday">{{ $wish->slot->starts_at->translatedFormat('D') }}</span>
                        <span class="date-block__day">{{ $wish->slot->starts_at->format('d') }}</span>
                        <span class="date-block__time">{{ $wish->slot->starts_at->format('H:i') }}</span>
                    </div>
                    <div>
                        <div class="booking-card__head">
                            <h2 id="wunsch-{{ $wish->id }}">{{ $wish->offer->title }}</h2>
                            <x-badge variant="hint">Wunsch {{ $wish->priority }} · Verteilung offen</x-badge>
                        </div>
                        <p class="muted">{{ $wish->slot->dateLabel() }}, {{ $wish->slot->timeRange() }} · {{ $wish->offer->locationLabel() }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <h2 class="section-title">Vergangen</h2>
    <div class="booking-stack">
        @forelse ($past as $booking)
            @include('bookings._card', ['booking' => $booking, 'past' => true])
        @empty
            <p class="card empty-state">Noch keine vergangenen Termine.</p>
        @endforelse
    </div>
@endsection
