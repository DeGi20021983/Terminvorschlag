<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminbuchung</title>
</head>
<body>
    <main>
        <h1>Terminbuchung</h1>
        <p>Angemeldet als {{ $user->name() }}{{ $user->department ? ', '.$user->department : '' }}.</p>
    </main>
</body>
</html>
