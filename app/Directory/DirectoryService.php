<?php

namespace App\Directory;

/**
 * Liest Stammdaten und Gruppenzugehörigkeit einer Person aus dem Verzeichnis.
 *
 * Die Anwendung kennt keine eigenen Passwörter. Sie vertraut dem Benutzernamen,
 * den IIS nach der Windows-Anmeldung setzt, und fragt das AD nur nach Daten ab.
 */
interface DirectoryService
{
    /**
     * @return DirectoryPerson|null null, wenn das Konto nicht (mehr) existiert
     *
     * @throws DirectoryUnavailableException wenn das Verzeichnis nicht erreichbar ist
     */
    public function lookup(string $username): ?DirectoryPerson;
}
