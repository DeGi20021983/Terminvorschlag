<?php

namespace Tests\Support;

use App\Directory\DirectoryPerson;
use App\Directory\DirectoryService;
use App\Directory\DirectoryUnavailableException;

/**
 * Ersetzt das AD in Tests. Konten lassen sich zur Laufzeit anlegen,
 * ändern und per Ausfall simulieren.
 */
class FakeDirectoryService implements DirectoryService
{
    /** @var array<string, DirectoryPerson> */
    private array $people = [];

    private bool $unavailable = false;

    public function add(
        string $username,
        string $displayName = 'Test Person',
        ?string $department = 'Bereich Nord',
        ?string $email = null,
        bool $isOrganizer = false,
        bool $isAdmin = false,
    ): self {
        $this->people[strtolower($username)] = new DirectoryPerson(
            username: strtolower($username),
            displayName: $displayName,
            department: $department,
            email: $email ?? strtolower($username).'@example.de',
            isOrganizer: $isOrganizer,
            isAdmin: $isAdmin,
        );

        return $this;
    }

    public function setUnavailable(bool $unavailable = true): self
    {
        $this->unavailable = $unavailable;

        return $this;
    }

    public function lookup(string $username): ?DirectoryPerson
    {
        if ($this->unavailable) {
            throw new DirectoryUnavailableException('simuliert');
        }

        return $this->people[strtolower($username)] ?? null;
    }
}
