<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Directory (LDAP/LDAPS)
    |--------------------------------------------------------------------------
    | Das Dienstkonto braucht nur Leserechte auf Personenobjekte und Gruppen.
    | Passwörter gehören nicht in das Repository, sondern in die .env.
    */

    'ldap' => [
        'uri' => env('LDAP_URI', 'ldaps://ad.example.local:636'),
        'bind_dn' => env('LDAP_BIND_DN'),
        'bind_password' => env('LDAP_BIND_PASSWORD'),
        'base_dn' => env('LDAP_BASE_DN', 'DC=example,DC=local'),
        'tls_ca_file' => env('LDAP_TLS_CA_FILE'),
        'timeout' => (int) env('LDAP_TIMEOUT', 5),
    ],

    /*
    | Vollständige Distinguished Names der Rollengruppen.
    | Beispiel: CN=Termine-Organisatoren,OU=Gruppen,DC=example,DC=local
    */
    'groups' => [
        'organizer' => env('LDAP_GROUP_ORGANIZER', ''),
        'admin' => env('LDAP_GROUP_ADMIN', ''),
    ],

    /*
    | Wie lange eine AD-Abfrage (inkl. Rollen) zwischengespeichert wird, in Sekunden.
    */
    'cache_ttl' => (int) env('LDAP_CACHE_TTL', 300),

];
