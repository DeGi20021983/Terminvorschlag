@extends('layouts.app')

@section('title', 'Wunschverteilung')

@section('content')
    @include('partials.flash')

    <header class="page-header">
        <h1>Wunschverteilung</h1>
        <p>Verteilung der Wunschtermine für Ihre Angebote mit Wunschterminen.</p>
    </header>

    @if ($offers->isEmpty())
        <p class="card empty-state">Sie haben kein Angebot mit Wunschterminen.</p>
    @else
        <form method="get" action="{{ route('manage.wishes.index') }}" class="filters">
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

        <p class="card empty-state">Für dieses Angebot liegen noch keine Wünsche vor.</p>
    @endif
@endsection
