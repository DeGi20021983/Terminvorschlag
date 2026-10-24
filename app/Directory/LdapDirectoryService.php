<?php

namespace App\Directory;

use LDAP\Connection;

/**
 * AD-Abfrage per LDAP(S) mit einem Dienstkonto (nur Leserechte).
 *
 * Gruppen werden über die Matching-Regel „in chain“ geprüft. Sie erfasst auch
 * verschachtelte Gruppen, sodass eine Mitgliedschaft über eine Untergruppe
 * ebenfalls zählt.
 */
class LdapDirectoryService implements DirectoryService
{
    private const IN_CHAIN_RULE = '1.2.840.113556.1.4.1941';

    /**
     * @param  array{uri: string, bind_dn: ?string, bind_password: ?string, base_dn: string, tls_ca_file: ?string, timeout: int}  $connection
     * @param  array{organizer: string, admin: string}  $groups  Gruppen als vollständiger DN
     */
    public function __construct(
        private readonly array $connection,
        private readonly array $groups,
    ) {}

    public function lookup(string $username): ?DirectoryPerson
    {
        $ldap = $this->connect();

        try {
            $entry = $this->findPerson($ldap, $username);

            if ($entry === null) {
                return null;
            }

            return new DirectoryPerson(
                username: $username,
                displayName: $entry['displayname'] ?: $username,
                department: $entry['department'] ?: $this->organizationalUnit($entry['dn']),
                email: $entry['mail'] ?: null,
                isOrganizer: $this->isMember($ldap, $username, $this->groups['organizer']),
                isAdmin: $this->isMember($ldap, $username, $this->groups['admin']),
            );
        } finally {
            ldap_unbind($ldap);
        }
    }

    /**
     * @return Connection|resource
     */
    private function connect()
    {
        if (! extension_loaded('ldap')) {
            throw new DirectoryUnavailableException('PHP-Erweiterung ldap fehlt.');
        }

        $ldap = ldap_connect($this->connection['uri']);

        if ($ldap === false) {
            throw new DirectoryUnavailableException('Verzeichnis nicht erreichbar.');
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, $this->connection['timeout']);

        if (! empty($this->connection['tls_ca_file'])) {
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $this->connection['tls_ca_file']);
            ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);
        }

        $bound = @ldap_bind($ldap, $this->connection['bind_dn'], $this->connection['bind_password']);

        if (! $bound) {
            ldap_unbind($ldap);

            throw new DirectoryUnavailableException('Anmeldung am Verzeichnis fehlgeschlagen.');
        }

        return $ldap;
    }

    /**
     * @return array{dn: string, displayname: ?string, department: ?string, mail: ?string}|null
     */
    private function findPerson($ldap, string $username): ?array
    {
        $filter = sprintf(
            '(&(objectCategory=person)(objectClass=user)(sAMAccountName=%s))',
            ldap_escape($username, '', LDAP_ESCAPE_FILTER)
        );

        $result = ldap_search($ldap, $this->connection['base_dn'], $filter, [
            'distinguishedName', 'displayName', 'department', 'mail',
        ], 0, 1);

        $entries = $result === false ? [] : ldap_get_entries($ldap, $result);

        if (($entries['count'] ?? 0) === 0) {
            return null;
        }

        $entry = $entries[0];

        return [
            'dn' => $entry['distinguishedname'][0] ?? '',
            'displayname' => $entry['displayname'][0] ?? null,
            'department' => $entry['department'][0] ?? null,
            'mail' => $entry['mail'][0] ?? null,
        ];
    }

    private function isMember($ldap, string $username, string $groupDn): bool
    {
        if ($groupDn === '') {
            return false;
        }

        $filter = sprintf(
            '(&(objectCategory=person)(objectClass=user)(sAMAccountName=%s)(memberOf:%s:=%s))',
            ldap_escape($username, '', LDAP_ESCAPE_FILTER),
            self::IN_CHAIN_RULE,
            ldap_escape($groupDn, '', LDAP_ESCAPE_FILTER)
        );

        $result = ldap_search($ldap, $this->connection['base_dn'], $filter, ['dn'], 0, 1);

        return $result !== false && ldap_count_entries($ldap, $result) > 0;
    }

    /**
     * Fällt auf die erste OU im DN zurück, wenn das Attribut „department“ leer ist.
     */
    private function organizationalUnit(string $dn): ?string
    {
        if (preg_match('/OU=([^,]+)/i', $dn, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
