@extends('layouts.app')

@section('title', 'Teilnehmerliste')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Teilnehmerliste</h1>
        <p>Die aktuelle Belegung Ihrer Termine. Die Liste aktualisiert sich automatisch.</p>
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
        @php($frei = $slots->filter(fn ($termin) => $termin->activeBooking === null && $termin->starts_at->isFuture()))

        <div class="toolbar">
            <a class="btn btn--secondary" href="{{ route('manage.participants.xlsx', $selected) }}">Excel-Export</a>
            <a class="btn btn--secondary" href="{{ route('manage.participants.csv', $selected) }}">CSV-Export</a>
            <a class="btn btn--secondary" href="{{ route('manage.participants.print', $selected) }}">Anwesenheitsliste drucken</a>
        </div>

        <p class="status-line" role="status" data-live-status>
            Stand: {{ $updatedAt->format('H:i') }} Uhr · aktualisiert sich automatisch
        </p>

        <div tabindex="0" class="table-wrap table-wrap--spaced" data-live-url="{{ route('manage.participants.live', $selected) }}">
            @include('manage.participants._table', ['offer' => $selected, 'slots' => $slots])
        </div>

        <div class="manage-layout section-gap section-gap--top">
            <section class="card" aria-labelledby="eintragen-titel">
                <h2 id="eintragen-titel">Person manuell eintragen</h2>
                <form method="post" action="{{ route('manage.participants.add', $selected) }}">
                    @csrf
                    <div class="field">
                        <label for="add-slot">Freier Termin</label>
                        <select id="add-slot" name="slot_id" required @disabled($frei->isEmpty())>
                            @foreach ($frei as $termin)
                                <option value="{{ $termin->id }}">{{ $termin->dateLabel() }}, {{ $termin->timeRange() }}</option>
                            @endforeach
                        </select>
                        @if ($frei->isEmpty())
                            <p class="field__help">Es gibt keinen freien Termin mehr.</p>
                        @endif
                    </div>
                    <div class="field">
                        <label for="add-user">Benutzername (Kennung)</label>
                        <input id="add-user" name="username" type="text" maxlength="64" required autocomplete="off"
                               pattern="[A-Za-z0-9._\-]+" @error('username') aria-invalid="true" aria-describedby="add-user-fehler" @enderror>
                        <p class="field__help">Die Person wird im Verzeichnis gesucht und eingetragen.</p>
                        @error('username')<p id="add-user-fehler" class="field__help hint">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn--primary" type="submit" @disabled($frei->isEmpty())>Eintragen</button>
                </form>
            </section>

            <section class="card" aria-labelledby="nachricht-titel">
                <h2 id="nachricht-titel">Nachricht an Teilnehmende</h2>
                <form method="post" action="{{ route('manage.participants.message', $selected) }}"
                      data-confirm="Nachricht an alle gebuchten Personen dieses Angebots senden?">
                    @csrf
                    <div class="field">
                        <label for="msg-subject">Betreff</label>
                        <input id="msg-subject" name="subject" type="text" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label for="msg-body">Nachricht</label>
                        <textarea id="msg-body" name="body" maxlength="5000" required></textarea>
                        <p class="field__help">Die Nachricht geht als reiner Text an alle gebuchten Personen.</p>
                    </div>
                    <button class="btn btn--primary" type="submit">Nachricht senden</button>
                </form>
            </section>
        </div>
    @else
        <p class="card empty-state">Sie haben noch keine Angebote.</p>
    @endif
@endsection
