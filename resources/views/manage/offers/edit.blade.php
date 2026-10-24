@extends('layouts.app')

@section('title', $offer->title)

@section('content')
    @php
        use App\Enums\OfferStatus;

        $belegt = $slots->filter(fn ($termin) => $termin->activeBooking !== null)->count();
    @endphp

    @include('partials.flash')

    <nav class="breadcrumb" aria-label="Pfad">
        <ol>
            <li><a href="{{ route('manage.offers.index') }}">Angebote verwalten</a></li>
            <li aria-current="page">{{ $offer->title }}</li>
        </ol>
    </nav>

    <header class="page-header">
        <h1>{{ $offer->title }}</h1>
        <p class="meta-line">
            Status: <x-badge :variant="$offer->isCancelled() ? 'danger' : 'accent'">{{ $offer->status->label() }}</x-badge>
            · {{ $slots->count() }} Termine · {{ $belegt }} belegt
        </p>
        @if ($offer->status === OfferStatus::Draft)
            <form method="post" action="{{ route('manage.offers.publish', $offer) }}">
                @csrf
                <button class="btn btn--primary" type="submit">Angebot veröffentlichen</button>
            </form>
        @endif
    </header>

    <div class="manage-layout">
        <section class="card" aria-labelledby="formular-titel">
            <h2 id="formular-titel">Angebot</h2>
            @include('manage.offers._form', [
                'offer' => $offer,
                'locations' => $locations,
                'action' => route('manage.offers.update', $offer),
                'method' => 'PUT',
                'submit' => 'Angebot speichern',
            ])
        </section>

        <section aria-labelledby="termine-titel">
            <h2 id="termine-titel">Termine</h2>

            <form method="post" action="{{ route('manage.slots.grid', $offer) }}">
                @csrf
                <fieldset class="fieldset">
                    <legend>Zeitraster für einen Tag</legend>
                    <div class="form-row form-row--3">
                        <div class="field">
                            <label for="grid-date">Tag</label>
                            <input id="grid-date" name="date" type="date" required>
                        </div>
                        <div class="field">
                            <label for="grid-from">von</label>
                            <input id="grid-from" name="from" type="time" required value="09:00">
                        </div>
                        <div class="field">
                            <label for="grid-to">bis</label>
                            <input id="grid-to" name="to" type="time" required value="12:00">
                        </div>
                    </div>
                    <div class="form-row form-row--2">
                        <div class="field">
                            <label for="grid-minutes">Länge je Termin</label>
                            <select id="grid-minutes" name="minutes">
                                @foreach ([15, 20, 30, 45, 60, 90, 120] as $minutes)
                                    <option value="{{ $minutes }}" @selected($minutes === 30)>{{ $minutes }} Minuten</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" class="field field--end">
                            <button class="btn btn--secondary" type="submit">Zeitraster anlegen</button>
                        </div>
                    </div>
                </fieldset>
            </form>

            <form method="post" action="{{ route('manage.slots.store', $offer) }}">
                @csrf
                <fieldset class="fieldset">
                    <legend>Einzeltermin</legend>
                    <div class="form-row form-row--3">
                        <div class="field">
                            <label for="single-date">Datum</label>
                            <input id="single-date" name="date" type="date" required>
                        </div>
                        <div class="field">
                            <label for="single-start">Beginn</label>
                            <input id="single-start" name="start" type="time" required>
                        </div>
                        <div class="field">
                            <label for="single-end">Ende</label>
                            <input id="single-end" name="end" type="time" required>
                        </div>
                    </div>
                    <button class="btn btn--secondary" type="submit">Termin anlegen</button>
                </fieldset>
            </form>

            <div tabindex="0" class="table-wrap">
                <table class="table">
                    <caption>Alle Termine</caption>
                    <thead>
                        <tr>
                            <th scope="col">Termin</th>
                            <th scope="col">Status</th>
                            <th scope="col">Gebucht von</th>
                            <th scope="col">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($slots as $termin)
                            <tr>
                                <td>
                                    {{ $termin->dateLabel() }}<br>
                                    <span class="muted">{{ $termin->timeRange() }}</span>
                                </td>
                                <td>
                                    @if ($termin->activeBooking)
                                        <x-badge variant="booked">Belegt</x-badge>
                                    @else
                                        <x-badge variant="free">Frei</x-badge>
                                    @endif
                                </td>
                                <td>{{ $termin->activeBooking?->user?->name() ?? '–' }}</td>
                                <td>
                                    <div class="actions">
                                        <details class="disclosure">
                                            <summary>Kopieren<span class="sr-only"> von {{ $termin->dateLabel() }}</span></summary>
                                            <form method="post" action="{{ route('manage.slots.copy', $termin) }}">
                                                @csrf
                                                <div class="field">
                                                    <label for="copy-date-{{ $termin->id }}">Neues Datum</label>
                                                    <input id="copy-date-{{ $termin->id }}" name="date" type="date" required>
                                                </div>
                                                <div class="field">
                                                    <label for="copy-start-{{ $termin->id }}">Startzeit</label>
                                                    <input id="copy-start-{{ $termin->id }}" name="start" type="time" required value="{{ $termin->starts_at->format('H:i') }}">
                                                </div>
                                                <button class="btn btn--small btn--primary" type="submit">Kopie anlegen</button>
                                            </form>
                                        </details>

                                        @if ($termin->activeBooking)
                                            <form method="post" action="{{ route('manage.bookings.remove', $termin->activeBooking) }}" class="inline-form"
                                                  data-confirm="Diese Person wirklich austragen? Der Termin wird danach wieder frei.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn--small" type="submit">Austragen</button>
                                            </form>
                                        @else
                                            <form method="post" action="{{ route('manage.slots.destroy', $termin) }}" class="inline-form"
                                                  data-confirm="Diesen Termin wirklich löschen?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn--small btn--ghost" type="submit">Löschen</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Noch keine Termine angelegt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @unless ($offer->isCancelled())
        <section class="danger-zone" aria-labelledby="gefahr-titel">
            <h2 id="gefahr-titel">Gefahrenbereich</h2>
            <h3>Angebot absagen</h3>
            <p>Alle gebuchten Personen werden storniert und per E-Mail informiert. Das lässt sich nicht rückgängig machen.</p>
            <form method="post" action="{{ route('manage.offers.cancel', $offer) }}" data-confirm="Angebot wirklich absagen? Alle Buchungen werden storniert.">
                @csrf
                <div class="field">
                    <label for="cancel-note">Hinweis an die Teilnehmenden (optional)</label>
                    <textarea id="cancel-note" name="note" maxlength="500"></textarea>
                </div>
                <button class="btn btn--danger" type="submit">Angebot absagen</button>
            </form>
        </section>
    @endunless
@endsection
