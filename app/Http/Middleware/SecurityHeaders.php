<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt Sicherheitsheader. Seiten mit Personenbezug werden nicht zwischengespeichert.
 * Die CSP erlaubt nur eigene Skripte, Stile, Schriften und Bilder (keine CDNs).
 */
final class SecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; "
        ."font-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; "
        ."frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control', 'no-store, private');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
