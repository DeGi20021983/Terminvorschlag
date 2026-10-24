@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Administration</h1>
        <p>Grundeinstellungen, Standorte, Rollen, Löschfristen und Protokoll.</p>
    </header>

    <section class="section-gap" aria-labelledby="einstellungen-titel">
        <h2 id="einstellungen-titel">Grundeinstellungen</h2>
        <form method="post" action="{{ route('admin.settings') }}" class="card narrow">
            @csrf
            @method('PUT')
            <div class="form-row form-row--2">
                <div class="field">
                    <label for="wish_deadline_days_before">Standardfrist für Wunschtermine (Tage vor Buchungsbeginn)</label>
                    <input id="wish_deadline_days_before" name="wish_deadline_days_before" type="number" min="0" max="60" required
                           value="{{ old('wish_deadline_days_before', $settings['wish_deadline_days_before']) }}">
                </div>
                <div class="field">
                    <label for="reminder_hours_before">Erinnerung vor dem Termin (Stunden)</label>
                    <input id="reminder_hours_before" name="reminder_hours_before" type="number" min="1" max="168" required
                           value="{{ old('reminder_hours_before', $settings['reminder_hours_before']) }}">
                </div>
            </div>
            <div class="field">
                <label for="confirmation_text">Standardtext der Buchungsbestätigung</label>
                <textarea id="confirmation_text" name="confirmation_text" maxlength="1000" required>{{ old('confirmation_text', $settings['confirmation_text']) }}</textarea>
            </div>
            <button class="btn btn--primary" type="submit">Einstellungen speichern</button>
        </form>
    </section>

    <section class="section-gap" aria-labelledby="standorte-titel">
        <h2 id="standorte-titel">Standorte</h2>
        <div tabindex="0" class="table-wrap">
            <table class="table">
                <caption>Standorte für Angebote</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Adresse</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Aktion</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->address ?? '–' }}</td>
                            <td><x-badge :variant="$location->is_active ? 'free' : 'booked'">{{ $location->is_active ? 'Aktiv' : 'Inaktiv' }}</x-badge></td>
                            <td>
                                <details class="disclosure">
                                    <summary>Bearbeiten<span class="sr-only"> {{ $location->name }}</span></summary>
                                    <form method="post" action="{{ route('admin.locations.update', $location) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="field">
                                            <label for="loc-name-{{ $location->id }}">Name</label>
                                            <input id="loc-name-{{ $location->id }}" name="name" type="text" maxlength="120" required value="{{ $location->name }}">
                                        </div>
                                        <div class="field">
                                            <label for="loc-address-{{ $location->id }}">Adresse</label>
                                            <input id="loc-address-{{ $location->id }}" name="address" type="text" maxlength="255" value="{{ $location->address }}">
                                        </div>
                                        <div class="field field--check">
                                            <input id="loc-active-{{ $location->id }}" name="is_active" type="checkbox" value="1" @checked($location->is_active)>
                                            <label for="loc-active-{{ $location->id }}">Aktiv (für neue Angebote auswählbar)</label>
                                        </div>
                                        <button class="btn btn--small btn--primary" type="submit">Speichern</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Noch keine Standorte angelegt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="post" action="{{ route('admin.locations.store') }}" class="card narrow notice">
            @csrf
            <h3>Neuer Standort</h3>
            <div class="form-row form-row--2">
                <div class="field">
                    <label for="new-loc-name">Name</label>
                    <input id="new-loc-name" name="name" type="text" maxlength="120" required>
                </div>
                <div class="field">
                    <label for="new-loc-address">Adresse</label>
                    <input id="new-loc-address" name="address" type="text" maxlength="255">
                </div>
            </div>
            <div class="field field--check">
                <input id="new-loc-active" name="is_active" type="checkbox" value="1" checked>
                <label for="new-loc-active">Aktiv</label>
            </div>
            <button class="btn btn--secondary" type="submit">Standort anlegen</button>
        </form>
    </section>

    <section class="section-gap" aria-labelledby="rollen-titel">
        <h2 id="rollen-titel">Rollen und AD-Gruppen</h2>
        <div tabindex="0" class="table-wrap">
            <table class="table">
                <caption>Zuordnung der Rollen zu den Gruppen im Verzeichnis</caption>
                <thead>
                    <tr>
                        <th scope="col">Rolle</th>
                        <th scope="col">AD-Gruppe</th>
                        <th scope="col">Berechtigungen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>{{ $role['label'] }}</td>
                            <td>{{ $role['group'] }}</td>
                            <td>{{ $role['rights'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-gap" aria-labelledby="loeschung-titel">
        <h2 id="loeschung-titel">Löschfristen</h2>
        <p class="card">
            @if ($lastPurge)
                Letzter Löschlauf: {{ $lastPurge->started_at->translatedFormat('d.m.Y H:i') }} Uhr,
                Ergebnis: {{ $lastPurge->status === 'success' ? 'erfolgreich' : 'fehlgeschlagen' }}.
            @else
                Es ist noch kein Löschlauf protokolliert.
            @endif
            Der Lauf startet täglich um 03:10 Uhr.
        </p>
        @if ($purgeRuns->isNotEmpty())
            <div tabindex="0" class="table-wrap">
                <table class="table">
                    <caption>Letzte Löschläufe</caption>
                    <thead>
                        <tr>
                            <th scope="col">Start</th>
                            <th scope="col">Ergebnis</th>
                            <th scope="col">Buchungen gelöscht</th>
                            <th scope="col">Termine als belegt markiert</th>
                            <th scope="col">Konten entfernt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purgeRuns as $run)
                            <tr>
                                <td>{{ $run->started_at->translatedFormat('d.m.Y H:i') }}</td>
                                <td>{{ $run->status === 'success' ? 'Erfolgreich' : ($run->status === 'failed' ? 'Fehlgeschlagen' : 'Läuft') }}</td>
                                <td>{{ $run->deleted_bookings }}</td>
                                <td>{{ $run->anonymized_slots }}</td>
                                <td>{{ $run->deleted_users }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="section-gap" aria-labelledby="protokoll-titel">
        <h2 id="protokoll-titel">Protokoll</h2>
        <p class="muted">Änderungen durch Organisatoren und Administratoren. Einträge werden nach {{ config('terminvorschlag.audit_retention_days') }} Tagen gelöscht.</p>

        <form method="get" action="{{ route('admin.index') }}" class="filters" role="search" aria-label="Protokoll filtern">
            <div class="field">
                <label for="von">Von</label>
                <input id="von" name="von" type="date" value="{{ $filters['von'] ?? '' }}">
            </div>
            <div class="field">
                <label for="bis">Bis</label>
                <input id="bis" name="bis" type="date" value="{{ $filters['bis'] ?? '' }}">
            </div>
            <div class="field">
                <label for="aktion">Aktion</label>
                <select id="aktion" name="aktion">
                    <option value="">Alle</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['aktion'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="akteur">Benutzername</label>
                <input id="akteur" name="akteur" type="search" maxlength="64" value="{{ $filters['akteur'] ?? '' }}">
            </div>
            <button class="btn btn--secondary" type="submit">Filtern</button>
        </form>

        <div tabindex="0" class="table-wrap">
            <table class="table">
                <caption>Protokolleinträge</caption>
                <thead>
                    <tr>
                        <th scope="col">Zeitpunkt</th>
                        <th scope="col">Benutzername</th>
                        <th scope="col">Aktion</th>
                        <th scope="col">Angebot</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audit as $entry)
                        <tr>
                            <td>{{ $entry->created_at->translatedFormat('d.m.Y H:i:s') }}</td>
                            <td>{{ $entry->actor_username }}</td>
                            <td>{{ $entry->action }}</td>
                            <td>{{ $entry->offer_id ? '#'.$entry->offer_id : '–' }}</td>
                            <td>{{ $entry->details ? json_encode($entry->details, JSON_UNESCAPED_UNICODE) : '–' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Keine Einträge für diesen Filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="section-gap">{{ $audit->links() }}</div>
    </section>
@endsection
