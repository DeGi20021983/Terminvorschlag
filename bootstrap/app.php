<?php

use App\Http\Middleware\AuthenticateRemoteUser;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\RestrictNetwork;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reihenfolge: HTTPS, Hausnetz, Sicherheitsheader, dann Anmeldung über IIS.
        $middleware->web(prepend: [
            ForceHttps::class,
            RestrictNetwork::class,
            SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            AuthenticateRemoteUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
