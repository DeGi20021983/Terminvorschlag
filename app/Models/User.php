<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Ein Benutzer entspricht einem Windows-Konto. Rollen werden aus den
 * AD-Gruppen abgeleitet und bei jeder Anmeldung aktualisiert.
 *
 * @property int $id
 * @property string $username
 * @property string|null $display_name
 * @property string|null $department
 * @property string|null $email
 * @property bool $is_organizer
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'username',
        'display_name',
        'department',
        'email',
        'is_organizer',
        'is_admin',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_organizer' => 'boolean',
            'is_admin' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Anzeigename für Oberfläche und Mails. Fällt auf das Konto zurück.
     */
    public function name(): string
    {
        return $this->display_name ?: $this->username;
    }
}
