@extends('layouts.app')

@section('title', 'Neues Angebot')

@section('content')
    @include('partials.flash')

    <nav class="breadcrumb" aria-label="Pfad">
        <ol>
            <li><a href="{{ route('manage.offers.index') }}">Angebote verwalten</a></li>
            <li aria-current="page">Neues Angebot</li>
        </ol>
    </nav>

    <header class="page-header">
        <h1>Neues Angebot</h1>
        <p>Nach dem Anlegen fügen Sie die Termine hinzu und veröffentlichen das Angebot.</p>
    </header>

    <div class="card narrow">
        @include('manage.offers._form', [
            'offer' => null,
            'locations' => $locations,
            'action' => route('manage.offers.store'),
            'method' => 'POST',
            'submit' => 'Angebot anlegen',
        ])
    </div>
@endsection
