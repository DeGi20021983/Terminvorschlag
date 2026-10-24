<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leitet unverschlüsselte Anfragen auf HTTPS um (308, Methode bleibt erhalten).
 */
final class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('terminvorschlag.force_https') && ! $request->secure()) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
