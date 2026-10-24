<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Terminbuchung</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>

<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="{{ route('offers.index') }}">Terminbuchung</a>

        <nav aria-label="Hauptnavigation">
            <ul class="nav-list">
                <li><a href="{{ route('offers.index') }}" @if (request()->routeIs('offers.*')) aria-current="page" @endif>Angebote</a></li>
                <li><a href="{{ route('bookings.mine') }}" @if (request()->routeIs('bookings.mine')) aria-current="page" @endif>Meine Termine</a></li>

                @can('create', \App\Models\Offer::class)
                    <li><a href="{{ route('manage.offers.index') }}" @if (request()->routeIs('manage.offers.*')) aria-current="page" @endif>Angebote verwalten</a></li>
                    <li><a href="{{ route('manage.participants.index') }}" @if (request()->routeIs('manage.participants.*')) aria-current="page" @endif>Teilnehmerliste</a></li>
                    <li><a href="{{ route('manage.wishes.index') }}" @if (request()->routeIs('manage.wishes.*')) aria-current="page" @endif>Wunschverteilung</a></li>
                @endcan

                @can('admin')
                    <li><a href="{{ route('admin.index') }}" @if (request()->routeIs('admin.*')) aria-current="page" @endif>Administration</a></li>
                @endcan
            </ul>
        </nav>

        @auth
            <p class="user-info">
                {{ auth()->user()->name() }}
                @if (auth()->user()->department)
                    <span class="user-info__unit">{{ auth()->user()->department }}</span>
                @endif
            </p>
        @endauth
    </div>
</header>

<main id="inhalt" class="page" tabindex="-1">
    @yield('content')
</main>

<footer class="site-footer">
    <a href="{{ route('privacy') }}">Datenschutzhinweis</a>
</footer>

<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
