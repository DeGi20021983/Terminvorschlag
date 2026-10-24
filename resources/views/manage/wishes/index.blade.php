@extends('layouts.app')

@section('title', 'Wunschverteilung')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Wunschverteilung</h1>
        <p>Verteilung der Wunschtermine nach Ablauf der Frist. Vorschläge prüfen, ändern und freigeben.</p>
    </header>

    @if ($selected === null)
        <p class="card empty-state">Sie haben kein Angebot mit Wunschterminen.</p>
    @else
        <form method="get" action="{{ route('manage.wishes.index') }}" class="filters">
            <div class="field">
                <label for="offer">Angebot</label>
                <select id="offer" name="offer">
                    @foreach ($offers as $offer)
                        <option value="{{ $offer->id }}" @selected($selected->id === $offer->id)>{{ $offer->title }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn--secondary" type="submit">Anzeigen</button>
        </form>

        <p class="status-line" role="status">
            Frist: {{ $selected->wish_deadline?->translatedFormat('d.m.Y H:i') }} Uhr ·
            @if ($selected->wishes_released_at)
                Freigegeben am {{ $selected->wishes_released_at->translatedFormat('d.m.Y H:i') }} Uhr
            @elseif ($run)
                Berechnet am {{ $run->calculated_at->translatedFormat('d.m.Y H:i') }} Uhr, noch nicht freigegeben
            @else
                Noch nicht berechnet
            @endif
        </p>

        <div class="toolbar toolbar--spaced">
            <form method="post" action="{{ route('manage.wishes.calculate', $selected) }}" class="inline-form">
                @csrf
                <button class="btn btn--secondary" type="submit" @disabled(! $canCalculate)>Neu berechnen</button>
            </form>
            <form method="post" action="{{ route('manage.wishes.release', $selected) }}" class="inline-form"
                  data-confirm="Verteilung freigeben? Direkte Zuteilungen werden gebucht und alle Personen benachrichtigt.">
                @csrf
                <button class="btn btn--primary" type="submit" @disabled(! $canRelease)>Freigeben und benachrichtigen</button>
            </form>
            @unless ($canCalculate)
                <span class="muted">Die Berechnung ist nach Ablauf der Wunschfrist möglich.</span>
            @endunless
        </div>

        <div class="kpis">
            <div class="kpi"><div class="kpi__value">{{ $kpis['persons'] }}</div><div class="kpi__label">Personen mit Wünschen</div></div>
            <div class="kpi"><div class="kpi__value">{{ $kpis['first'] }}</div><div class="kpi__label">Erstwunsch erfüllt</div></div>
            <div class="kpi"><div class="kpi__value">{{ $kpis['lots'] }}</div><div class="kpi__label">Per Los</div></div>
            <div class="kpi"><div class="kpi__value">{{ $kpis['alternative'] }}</div><div class="kpi__label">Alternativen</div></div>
            <div class="kpi"><div class="kpi__value">{{ $kpis['none'] }}</div><div class="kpi__label">Ohne Termin</div></div>
        </div>

        <div tabindex="0" class="table-wrap">
            <table class="table">
                <caption>Wünsche und Vorschläge</caption>
                <thead>
                    <tr>
                        <th scope="col">Person</th>
                        <th scope="col">Wunsch 1</th>
                        <th scope="col">Wunsch 2</th>
                        <th scope="col">Wunsch 3</th>
                        <th scope="col">Vorschlag</th>
                        <th scope="col">Hinweis</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($assignment = $row['assignment'])
                        <tr>
                            <td>{{ $row['user']->name() }}</td>
                            @foreach ([1, 2, 3] as $prio)
                                <td>
                                    @if ($row['wishes']->has($prio))
                                        {{ $row['wishes'][$prio]->slot->dateLabel() }}, {{ $row['wishes'][$prio]->slot->timeRange() }}
                                    @else
                                        <span class="muted">–</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if ($assignment && $assignment->slot && $canRelease)
                                    <form method="post" action="{{ route('manage.wishes.assignment', $assignment) }}" class="actions">
                                        @csrf
                                        @method('PUT')
                                        <label class="sr-only" for="vorschlag-{{ $assignment->id }}">Vorschlag für {{ $row['user']->name() }}</label>
                                        <select id="vorschlag-{{ $assignment->id }}" name="slot_id">
                                            @foreach ($freeSlots as $termin)
                                                @if (! $usedBy->has($termin->id) || $usedBy[$termin->id] === $assignment->user_id)
                                                    <option value="{{ $termin->id }}" @selected($assignment->slot_id === $termin->id)>
                                                        {{ $termin->dateLabel() }}, {{ $termin->timeRange() }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button class="btn btn--small" type="submit">Ändern</button>
                                    </form>
                                @elseif ($assignment && $assignment->slot)
                                    {{ $assignment->slot->dateLabel() }}, {{ $assignment->slot->timeRange() }}
                                @else
                                    <span class="muted">–</span>
                                @endif
                            </td>
                            <td>{{ $assignment?->hint() ?? 'Noch nicht berechnet' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Für dieses Angebot liegen noch keine Wünsche vor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($draws->isNotEmpty())
            <section class="section-gap section-gap--top" aria-labelledby="los-titel">
                <h2 id="los-titel">Losprotokoll</h2>
                <div tabindex="0" class="table-wrap">
                    <table class="table">
                        <caption>Auslosungen bei Gleichstand um einen Termin</caption>
                        <thead>
                            <tr>
                                <th scope="col">Runde</th>
                                <th scope="col">Termin</th>
                                <th scope="col">Beteiligte</th>
                                <th scope="col">Gewinner oder Gewinnerin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($draws as $draw)
                                <tr>
                                    <td>Wunsch {{ $draw->round }}</td>
                                    <td>{{ $draw->slot->dateLabel() }}, {{ $draw->slot->timeRange() }}</td>
                                    <td>{{ count($draw->candidate_user_ids) }} Personen</td>
                                    <td>{{ $draw->winner?->name() ?? '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
@endsection
