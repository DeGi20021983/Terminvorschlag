<?php

namespace App\Services;

use App\Directory\DirectoryPerson;
use App\Models\User;

/**
 * Spiegelt Stammdaten und Rollen aus dem Verzeichnis in die lokale Tabelle „users“.
 */
final class UserProvisioner
{
    public function sync(DirectoryPerson $person, bool $signedIn = true): User
    {
        $attributes = [
            'display_name' => $person->displayName,
            'department' => $person->department,
            'email' => $person->email,
            'is_organizer' => $person->isOrganizer,
            'is_admin' => $person->isAdmin,
        ];

        if ($signedIn) {
            $attributes['last_seen_at'] = now();
        }

        return User::query()->updateOrCreate(['username' => $person->username], $attributes);
    }
}
