@extends('layouts.app')

@section('title', 'Angebote')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Angebote</h1>
        <p>Wählen Sie ein Angebot und buchen Sie Ihren Termin.</p>
    </header>

    <form method="get" action="{{ route('offers.index') }}" class="filters" role="search" aria-label="Angebote filtern">
        <div class="field">
            <label for="q">Suche</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}">
        </div>
        <div class="field">
            <label for="vergabe">Vergabe</label>
            <select id="vergabe" name="vergabe">
                <option value="">Alle</option>
                <option value="instant" @selected($filters['vergabe'] === 'instant')>Sofortbuchung</option>
                <option value="wishes" @selected($filters['vergabe'] === 'wishes')>Wunschtermine</option>
            </select>
        </div>
        <div class="field">
            <label for="bereich">Organisation</label>
            <select id="bereich" name="bereich">
                <option value="">Alle</option>
                @foreach ($areas as $area)
                    <option value="{{ $area }}" @selected($filters['bereich'] === $area)>{{ $area }}</option>
                @endforeach
            </select>
        </div>
        <div class="field field--check">
            <input id="frei" type="checkbox" name="frei" value="1" @checked($filters['frei'])>
            <label for="frei">Nur mit freien Terminen</label>
        </div>
        <button class="btn btn--secondary" type="submit">Filtern</button>
    </form>

    <ul class="card-grid">
        @forelse ($offers as $offer)
            <li>
                <article class="card offer-card" aria-labelledby="angebot-{{ $offer->id }}">
                    <div class="offer-card__meta">
                        <x-badge :variant="$offer->allocation_mode === \App\Enums\AllocationMode::Wishes ? 'hint' : 'accent'">
                            {{ $offer->allocation_mode->label() }}
                        </x-badge>
                        @if ($offer->area)
                            <span class="muted">{{ $offer->area }}</span>
                        @endif
                    </div>

                    <h2 id="angebot-{{ $offer->id }}">{{ $offer->title }}</h2>

                    @if ($offer->description)
                        <p>{{ \Illuminate\Support\Str::limit($offer->description, 140) }}</p>
                    @endif

                    <dl class="facts">
                        <div><dt>Ort</dt><dd>{{ $offer->locationLabel() }}</dd></div>
                        <div><dt>Buchbar bis</dt><dd>{{ $offer->bookable_until->translatedFormat('d.m.Y') }}</dd></div>
                        <div><dt>Ansprechperson</dt><dd>{{ $offer->contact_name }}</dd></div>
                    </dl>

                    <p class="progress">{{ $offer->free_slots_count }} von {{ $offer->slots_count }} Terminen frei</p>

                    @if ($offer->free_slots_count > 0)
                        <a class="btn btn--primary" href="{{ route('offers.show', $offer) }}">
                            Termine ansehen<span class="sr-only"> zu {{ $offer->title }}</span>
                        </a>
                    @else
                        <button class="btn" type="button" disabled>Ausgebucht</button>
                    @endif
                </article>
            </li>
        @empty
            <li class="card empty-state">Keine Angebote gefunden.</li>
        @endforelse
    </ul>
@endsection
