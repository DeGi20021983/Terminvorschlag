<?php

namespace App\Directory;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Hält AD-Abfragen kurz im Cache, damit nicht jeder Seitenaufruf das
 * Verzeichnis belastet. Rollenänderungen wirken nach Ablauf der TTL.
 */
class CachedDirectoryService implements DirectoryService
{
    public function __construct(
        private readonly DirectoryService $inner,
        private readonly Cache $cache,
        private readonly int $ttlSeconds,
    ) {}

    public function lookup(string $username): ?DirectoryPerson
    {
        $key = 'directory:person:'.$username;

        $cached = $this->cache->get($key);

        if ($cached instanceof DirectoryPerson) {
            return $cached;
        }

        $person = $this->inner->lookup($username);

        if ($person !== null) {
            $this->cache->put($key, $person, $this->ttlSeconds);
        }

        return $person;
    }
}
