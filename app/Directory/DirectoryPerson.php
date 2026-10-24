<?php

namespace App\Directory;

/**
 * Ergebnis einer AD-Abfrage: Stammdaten und Rollen einer Person.
 */
final readonly class DirectoryPerson
{
    public function __construct(
        public string $username,
        public string $displayName,
        public ?string $department,
        public ?string $email,
        public bool $isOrganizer,
        public bool $isAdmin,
    ) {}
}
