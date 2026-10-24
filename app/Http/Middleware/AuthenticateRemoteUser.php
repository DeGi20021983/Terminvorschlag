<?php

namespace App\Http\Middleware;

use App\Directory\DirectoryPerson;
use App\Directory\DirectoryService;
use App\Directory\DirectoryUnavailableException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meldet die Person an, die IIS per Windows-Authentifizierung übergeben hat.
 *
 * Die Anwendung hat keine eigenen Passwörter. Sie übernimmt den Servervariable
 * REMOTE_USER, gleicht das Konto mit dem AD ab und spiegelt Stammdaten und
 * Rollen in die Tabelle „users“.
 */
final class AuthenticateRemoteUser
{
    private const USERNAME_PATTERN = '/^[a-z0-9._-]{1,64}$/';

    public function __construct(
        private readonly DirectoryService $directory,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $username = $this->resolveUsername($request);

        if ($username === null) {
            abort(401, 'Anmeldung über Windows ist erforderlich.');
        }

        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            abort(403, 'Der Benutzername ist ungültig.');
        }

        $person = $this->lookupPerson($username);

        $user = $this->syncUser($person);

        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Liefert den Kontonamen ohne Domäne in Kleinbuchstaben.
     * Akzeptiert „DOMAIN\\name“ und „name@domain“.
     */
    private function resolveUsername(Request $request): ?string
    {
        $remoteUser = $request->server->get('REMOTE_USER');

        if (! is_string($remoteUser) || trim($remoteUser) === '') {
            // Nur lokal und nur mit ausdrücklicher Einstellung; in Produktion nie.
            $remoteUser = app()->environment('local') ? config('terminvorschlag.dev_user') : null;
        }

        if (! is_string($remoteUser) || trim($remoteUser) === '') {
            return null;
        }

        $name = preg_replace('/^.*\\\\/', '', trim($remoteUser));
        $name = preg_replace('/@.*$/', '', (string) $name);

        return strtolower((string) $name);
    }

    private function lookupPerson(string $username): DirectoryPerson
    {
        try {
            $person = $this->directory->lookup($username);
        } catch (DirectoryUnavailableException $exception) {
            report($exception);

            abort(503, 'Das Verzeichnis ist gerade nicht erreichbar. Bitte versuchen Sie es später erneut.');
        }

        if ($person === null) {
            abort(403, 'Ihr Konto ist im Verzeichnis nicht gefunden.');
        }

        return $person;
    }

    private function syncUser(DirectoryPerson $person): User
    {
        return User::updateOrCreate(
            ['username' => $person->username],
            [
                'display_name' => $person->displayName,
                'department' => $person->department,
                'email' => $person->email,
                'is_organizer' => $person->isOrganizer,
                'is_admin' => $person->isAdmin,
                'last_seen_at' => now(),
            ],
        );
    }
}
