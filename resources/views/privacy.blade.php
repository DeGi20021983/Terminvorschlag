@extends('layouts.app')

@section('title', 'Datenschutzhinweis')

@section('content')
    <header class="page-header">
        <h1>Datenschutzhinweis</h1>
    </header>

    <div class="card narrow">
        <h2>Welche Daten gespeichert werden</h2>
        <p>Gespeichert werden nur Name, dienstliche E-Mail-Adresse, Organisationseinheit und Ihre Buchungen. Diese Angaben stammen aus dem Verzeichnis der Windows-Anmeldung.</p>

        <h2>Wie lange</h2>
        <p>Personenbezogene Buchungsdaten werden nach dem Ende des Termins gelöscht. Erhalten bleibt eine anonyme Angabe, ob ein Termin belegt war.</p>

        <h2>Wer sieht die Daten</h2>
        <p>Mitarbeitende sehen bei belegten Terminen nur „belegt“, nie den Namen. Organisatoren sehen die Teilnehmenden ihrer eigenen Angebote. Änderungen durch Organisatoren und Administratoren werden protokolliert.</p>

        <h2>Keine Auswertung</h2>
        <p>Die Anwendung wertet Buchungen nicht mit Personenbezug aus.</p>
    </div>
@endsection
