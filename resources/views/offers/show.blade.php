@extends('layouts.app')

@section('title', $offer->title)

@section('content')
    @php
        $switching = $ownBooking !== null && $offer->one_booking_per_person;

        $state = function ($termin) use ($now, $userId, $bookable, $wishesOpen) {
            if ($termin->activeBooking !== null && $termin->activeBooking->user_id === $userId) {
                return 'mine';
            }

            if ($termin->activeBooking !== null) {
                return 'booked';
            }

            if ($termin->starts_at->lessThanOrEqualTo($now)) {
                return 'past';
            }

            if ($wishesOpen) {
                return 'wish';
            }

            return $bookable ? 'free' : 'closed';
        };

        $byDay = $slots->groupBy(fn ($termin) => $termin->starts_at->format('Y-m-d'));
    @endphp

    @include('partials.flash')

    <nav class="breadcrumb" aria-label="Pfad">
        <ol>
            <li><a href="{{ route('offers.index') }}">Angebote</a></li>
            <li aria-current="page">{{ $offer->title }}</li>
        </ol>
    </nav>

    <header class="page-header">
        <h1>{{ $offer->title }}</h1>
        <p class="meta-line">
            <x-badge :variant="$offer->allocation_mode === \App\Enums\AllocationMode::Wishes ? 'hint' : 'accent'">{{ $offer->allocation_mode->label() }}</x-badge>
            · {{ $offer->area ?? 'Bereich nicht angegeben' }}
            · {{ $offer->locationLabel() }}
            · Buchbar bis {{ $offer->bookable_until->translatedFormat('d.m.Y') }}
        </p>
        @if ($offer->description)
            <p>{{ $offer->description }}</p>
        @endif
    </header>

    <div class="booking-layout">
        <section aria-labelledby="termine-titel">
            <h2 id="termine-titel">Termine</h2>

            @if ($offer->allocation_mode === \App\Enums\AllocationMode::Wishes && ! $offer->instantBookingOpen())
                <div class="card notice">
                    <p>Dieses Angebot wird über Wunschtermine vergeben.
                        @if ($offer->wish_deadline)
                            Wünsche können bis {{ $offer->wish_deadline->translatedFormat('d.m.Y H:i') }} Uhr abgegeben werden.
                        @endif
                        Danach verteilt der Organisator die Termine. Freie Termine können anschließend direkt gebucht werden.</p>
                </div>
            @endif

            @if ($wishesOpen)
                <form method="post" action="{{ route('wishes.store', $offer) }}" class="card notice" aria-labelledby="wunsch-titel">
                    @csrf
                    <h2 id="wunsch-titel">Ihre Wunschtermine</h2>
                    <p class="muted">Wählen Sie zwei oder drei Termine in Ihrer Reihenfolge. Die Frist endet am
                        {{ $offer->wish_deadline->translatedFormat('d.m.Y H:i') }} Uhr. Danach verteilt der Organisator die Termine.</p>

                    @foreach ([1, 2, 3] as $prio)
                        <div class="field">
                            <label for="wunsch-{{ $prio }}">Wunsch {{ $prio }} <span class="muted">({{ $prio < 3 ? 'erforderlich' : 'optional' }})</span></label>
                            <select id="wunsch-{{ $prio }}" name="wishes[{{ $prio }}]">
                                <option value="">Kein Wunsch</option>
                                @foreach ($wishSlots as $termin)
                                    <option value="{{ $termin->id }}" @selected(($myWishes[$prio]->slot_id ?? null) === $termin->id)>
                                        {{ $termin->dateLabel() }}, {{ $termin->timeRange() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach

                    <button class="btn btn--primary" type="submit">Wünsche speichern</button>
                </form>
            @endif

            <div class="view-toggle" role="group" aria-label="Ansicht wählen">
                <button type="button" class="btn btn--small" data-view-target="ansicht-liste" aria-pressed="true">Liste</button>
                <button type="button" class="btn btn--small" data-view-target="ansicht-kalender" aria-pressed="false">Kalender</button>
            </div>

            <div id="ansicht-liste" data-view="liste">
                <ul class="slot-list">
                    @forelse ($slots as $termin)
                        <li>@include('offers._slot', ['termin' => $termin])</li>
                    @empty
                        <li class="card empty-state">Für dieses Angebot sind noch keine Termine angelegt.</li>
                    @endforelse
                </ul>
            </div>

            <div id="ansicht-kalender" data-view="kalender" hidden>
                <div class="calendar">
                    @foreach ($byDay as $day => $daySlots)
                        <section class="calendar__day" aria-labelledby="tag-{{ $day }}">
                            <h3 id="tag-{{ $day }}">{{ $daySlots->first()->dateLabel() }}</h3>
                            <ul class="slot-list">
                                @foreach ($daySlots as $termin)
                                    <li>@include('offers._slot', ['termin' => $termin])</li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="card summary" aria-labelledby="zusammenfassung-titel">
            <h2 id="zusammenfassung-titel">Zusammenfassung</h2>

            <dl>
                <div>
                    <dt>Angebot</dt>
                    <dd>{{ $offer->title }}</dd>
                </div>
                <div>
                    <dt>Termin</dt>
                    <dd>
                        @if ($selected)
                            {{ $selected->dateLabel() }}, {{ $selected->timeRange() }}
                        @else
                            Noch kein Termin gewählt
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Ort</dt>
                    <dd>{{ $offer->locationLabel() }}</dd>
                </div>
                <div>
                    <dt>Ansprechperson</dt>
                    <dd>{{ $offer->contact_name }}</dd>
                </div>
            </dl>

            @if ($switching)
                <p class="info">Ihr bisheriger Termin: {{ $ownBooking->slot->dateLabel() }}, {{ $ownBooking->slot->timeRange() }}.</p>
                <p class="hint-box">Der alte Platz bleibt bis zur Bestätigung des neuen Termins gebucht.</p>
            @endif

            <p class="info">Mit der Bestätigung ist der Platz sofort für andere gesperrt.</p>
            <p class="info">Sie erhalten eine Bestätigung mit einer .ics-Datei für Ihren Kalender. Stornieren können Sie bis Terminbeginn unter „Meine Termine“.</p>

            @if ($selected && $bookable)
                <form method="post" action="{{ $switching ? route('bookings.move', $ownBooking) : route('bookings.store', [$offer, $selected]) }}">
                    @csrf
                    @if ($switching)
                        <input type="hidden" name="slot_id" value="{{ $selected->id }}">
                    @endif
                    <button class="btn btn--primary btn--block" type="submit">
                        {{ $switching ? 'Umbuchung bestätigen' : 'Buchung bestätigen' }}
                    </button>
                </form>
            @else
                <button class="btn btn--primary btn--block" type="button" disabled>
                    {{ $switching ? 'Umbuchung bestätigen' : 'Buchung bestätigen' }}
                </button>
                <p class="info">Wählen Sie einen freien Termin, um fortzufahren.</p>
            @endif
        </aside>
    </div>
@endsection
