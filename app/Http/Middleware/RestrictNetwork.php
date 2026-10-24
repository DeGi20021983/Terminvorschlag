<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lässt Anfragen nur aus dem Hausnetz zu. Ist keine Liste gesetzt, gilt das
 * in Produktion als Fehler und sperrt alles (Fail-closed).
 */
final class RestrictNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        $networks = config('terminvorschlag.allowed_networks', []);

        if ($networks === []) {
            if (app()->isProduction()) {
                abort(403, 'Der Zugriff ist nicht freigegeben.');
            }

            return $next($request);
        }

        if (! IpUtils::checkIp((string) $request->ip(), $networks)) {
            abort(403, 'Der Zugriff ist nur aus dem Hausnetz möglich.');
        }

        return $next($request);
    }
}
