<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Terminbuchung</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <span class="brand">Terminbuchung</span>
    </div>
</header>
<main id="inhalt" class="error-page" tabindex="-1">
    @yield('content')
</main>
</body>
</html>
