<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zugriffsschutz
    |--------------------------------------------------------------------------
    */

    // Nur für lokale Entwicklung: Benutzername, der ohne IIS angenommen wird.
    // Wird nur ausgewertet, wenn APP_ENV=local ist.
    'dev_user' => env('TERMIN_DEV_USER'),

    // Komma-getrennte CIDR-Netze des Hausnetzes, z. B. "10.0.0.0/8,192.168.10.0/24".
    // Leer lassen nur in Entwicklung; in Produktion wird dann alles abgewiesen.
    'allowed_networks' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TERMIN_ALLOWED_NETWORKS', ''))
    ))),

    // HTTP-Anfragen werden auf HTTPS umgeleitet.
    'force_https' => (bool) env('TERMIN_FORCE_HTTPS', true),

    /*
    |--------------------------------------------------------------------------
    | Datenschutz und Löschfristen
    |--------------------------------------------------------------------------
    */

    // Protokolleinträge von Organisatoren und Administratoren werden nach dieser Zeit gelöscht.
    'audit_retention_days' => (int) env('TERMIN_AUDIT_RETENTION_DAYS', 365),

    // Personenbezogene Buchungsdaten werden nach Terminende so viele Stunden später gelöscht.
    'purge_grace_hours' => (int) env('TERMIN_PURGE_GRACE_HOURS', 0),

    /*
    |--------------------------------------------------------------------------
    | Vorgaben (werden in der Administration überschrieben)
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'wish_deadline_days_before' => (int) env('TERMIN_DEFAULT_WISH_DEADLINE_DAYS', 3),
        'reminder_hours_before' => (int) env('TERMIN_DEFAULT_REMINDER_HOURS', 24),
        'confirmation_text' => env(
            'TERMIN_DEFAULT_CONFIRMATION_TEXT',
            'Bitte sagen Sie den Termin rechtzeitig ab, falls Sie verhindert sind.'
        ),
    ],

];
