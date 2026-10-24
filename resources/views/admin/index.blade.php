@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Administration</h1>
        <p>Grundeinstellungen, Rollen und Löschfristen.</p>
    </header>

    <section aria-labelledby="standorte-titel" class="section-gap">
        <h2 id="standorte-titel">Standorte</h2>
        <div class="table-wrap">
            <table class="table">
                <caption>Standorte für Angebote</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Adresse</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->address ?? '–' }}</td>
                            <td>{{ $location->is_active ? 'Aktiv' : 'Inaktiv' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-state">Noch keine Standorte angelegt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="rollen-titel" class="section-gap">
        <h2 id="rollen-titel">Rollen und AD-Gruppen</h2>
        <div class="table-wrap">
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

    <section aria-labelledby="loeschung-titel">
        <h2 id="loeschung-titel">Löschfristen</h2>
        <p class="card">
            @if ($lastPurge)
                Letzter Löschlauf: {{ $lastPurge->started_at->translatedFormat('d.m.Y H:i') }} Uhr,
                Ergebnis: {{ $lastPurge->status === 'success' ? 'erfolgreich' : 'fehlgeschlagen' }}.
            @else
                Es ist noch kein Löschlauf protokolliert.
            @endif
        </p>
    </section>
@endsection
