<?php

namespace App\Directory;

/**
 * Verzeichnis aus der Konfiguration, nur für lokale Entwicklung und Demos ohne AD.
 * In Produktion wird dieser Dienst nicht gebunden (siehe AppServiceProvider).
 */
final class StaticDirectoryService implements DirectoryService
{
    /**
     * @param  array<string, array{name: string, department?: string, email?: string, organizer?: bool, admin?: bool}>  $people
     */
    public function __construct(
        private readonly array $people,
    ) {}

    public function lookup(string $username): ?DirectoryPerson
    {
        $row = $this->people[$username] ?? null;

        if ($row === null) {
            return null;
        }

        return new DirectoryPerson(
            username: $username,
            displayName: $row['name'],
            department: $row['department'] ?? null,
            email: $row['email'] ?? null,
            isOrganizer: (bool) ($row['organizer'] ?? false),
            isAdmin: (bool) ($row['admin'] ?? false),
        );
    }
}
