@extends('layouts.app')

@section('title', 'Angebote verwalten')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Angebote verwalten</h1>
        <p>Angebote, deren Termine und Buchungszeiträume, die Sie verantworten.</p>
    </header>

    <div class="toolbar">
        <a class="btn btn--primary" href="{{ route('manage.offers.create') }}">Neues Angebot</a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <caption>Ihre Angebote</caption>
            <thead>
                <tr>
                    <th scope="col">Titel</th>
                    <th scope="col">Vergabe</th>
                    <th scope="col">Status</th>
                    <th scope="col">Buchbar bis</th>
                    <th scope="col">Termine</th>
                    <th scope="col"><span class="sr-only">Aktion</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($offers as $offer)
                    <tr>
                        <td>{{ $offer->title }}</td>
                        <td>{{ $offer->allocation_mode->label() }}</td>
                        <td><x-badge :variant="$offer->isCancelled() ? 'danger' : 'accent'">{{ $offer->status->label() }}</x-badge></td>
                        <td>{{ $offer->bookable_until->translatedFormat('d.m.Y') }}</td>
                        <td>{{ $offer->slots_count }}</td>
                        <td><a class="btn btn--small" href="{{ route('manage.offers.edit', $offer) }}">Bearbeiten<span class="sr-only"> {{ $offer->title }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Sie haben noch keine Angebote angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
